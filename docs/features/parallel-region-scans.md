# Parallel multi-region scans — solution design

**Status:** proposed
**Date:** 2026-08-01
**Problem:** a full scan takes ~10 minutes. We want it materially faster.

---

## 1. What is actually happening today

The headline finding is that **the fan-out architecture is already parallel. The
deployment runs one worker process.** Region jobs are independent and are already
dispatched one per (service, region) — they just get executed strictly one at a time.

### The dispatch path

1. `ScansController@store` ([ScansController.php:288](../../app/app/Http/Controllers/Api/ScansController.php#L288))
   dispatches one `ProcessAuditScanJob`.
2. `ProcessAuditScanJob` ([ProcessAuditScanJob.php:102](../../app/app/Jobs/ProcessAuditScanJob.php#L102))
   assumes the role, then for each selected scan type either runs it inline (global
   services) or fans out one `ProcessRegionScanJob` per region.
3. Each `ProcessRegionScanJob` collects data for its own (service, region), evaluates
   findings, and calls `Scan::checkAndMarkRegionBasedScanComplete()`.

### The fan-out size

11 services ship in `rules/tasks/`. Nine are regional (`cloudtrail`, `dynamodb`, `ec2`,
`elbv2`, `kms`, `lambda`, `rds`, `sns`, `sqs`); two are global (`iam`, `s3`).
`getAvailableRegions()` returns the account's enabled regions — typically **17**.

> 9 regional services × 17 regions = **~153 region jobs per full scan**

Region pruning (`SCAN_PRUNE_REGIONS_WITH_TAGGING`, [config/scan.php:24](../../app/config/scan.php#L24))
would cut this substantially but is **off by default**, for the documented and correct
reason that it can silently mark an untagged region clean.

### The bottleneck: one worker consumes all 153

Every self-hosted install ends up here:

- `docker/installer/scripts/install-messaging.sh:213-214` writes
  `SCAN_QUEUE_CONNECTION=database` and `SCAN_REGION_QUEUE_CONNECTION=database` into
  `generated/teemops.env`. So even on an SQS-configured install, **scan jobs run on the
  database queue**.
- That queue is drained by exactly one process:
  - `docker/app/worker-supervisord.conf:22` — one `tops-queue` program, `numprocs`
    unset (supervisord default: 1), consuming `default,teemops_audit,teemops_audit_region`.
  - `docker/app/worker-entrypoint.sh:32` — the no-SQS path, a bare single
    `exec php artisan queue:work`.

For contrast, the legacy EC2 supervisor configs ran `numprocs=8`
([tops_scan_region_q.conf](../../infra/deploy/files/supervisor/tops_scan_region_q.conf)).
The Docker deployment lost that and nobody noticed, because nothing is functionally
broken — it is just serial.

153 jobs × ~4s each ≈ 612s. That is the 10 minutes.

### The second bottleneck: findings evaluation is quadratic

`ProcessRegionScanJob` calls `FindingsEngine::evaluateScan()` on **every** region job
([ProcessRegionScanJob.php:120](../../app/app/Jobs/ProcessRegionScanJob.php#L120)). That
method re-evaluates the **entire scan from scratch** every time:

```php
$scanDetails = ScanDetail::where('scan_id', $scan->id)->get();   // FindingsEngine.php:62
```

It loads every detail row collected so far into memory, groups it, then runs every rule
in the ruleset across the whole collection. Region job #150 re-does the work of jobs
1–149. Across 153 jobs this is O(N²) in detail rows for a result that only needs
computing once. `createFinding()` also issues an individual `SELECT` per candidate row to
dedupe ([FindingsEngine.php:146](../../app/app/Services/RulesEngine/FindingsEngine.php#L146)).

`checkAndMarkRegionBasedScanComplete()` adds a `DISTINCT (region, service)` scan over all
the scan's detail rows, also once per job, with no supporting index (`scan_details` has
no index covering `region`).

**Caveat:** this breakdown is read off the code, not measured. T1 below exists to confirm
the split before we spend effort on the wrong half.

---

## 2. Why this is not "set numprocs=8 and ship"

Several pieces of shared state are currently protected only by the fact that there is one
worker. Enabling concurrency without fixing them first produces **wrong data**, not just
faster scans.

### 2a. Premature completion — the blocker

This is the most serious finding in this document.

`ProcessAuditScanJob` resets the counter to `0`
([ProcessAuditScanJob.php:88](../../app/app/Jobs/ProcessAuditScanJob.php#L88)), then
dispatches, then accumulates `expected_regions_count` **per service**, after each
service's regions have been pushed
([ProcessAuditScanJob.php:232](../../app/app/Jobs/ProcessAuditScanJob.php#L232)). During
orchestration the counter climbs 0 → 17 → 34 → … → 153.

Any region job completing inside that window calls
`checkAndMarkRegionBasedScanComplete()`, which does:

```php
$expectedRegionJobs = $this->expected_regions_count ?? null;    // 0 — not null
$shouldComplete = $processedRegionJobs >= $expectedRegionJobs;  // 1 >= 0 → true
```

`$timedOut` is false (processed ≥ expected), so the scan is written as
`status = completed`, **`is_partial = false`** — reported as clean and complete with 1 of
153 region-service pairs actually scanned.

For a security scanner this is the worst available failure mode, and it is exactly what
the region-pruning config warns about: a region reported compliant that nobody looked at.

Today it is fully masked, because a single worker means the orchestrator always finishes
before any region job is dequeued. **Add a second worker and it fires on the first scan.**

### 2b. Duplicate findings

`createFinding()` is a read-then-write:

```php
$existing = ScanResult::where(...)->first();   // FindingsEngine.php:146
if ($existing) { return; }
ScanResult::create([...]);                     // FindingsEngine.php:157
```

Because every region job evaluates the *whole* scan, two workers finishing regions at the
same moment will both load the same detail rows, both find the dedupe `SELECT` empty, and
both `INSERT`. There is **no unique constraint on `scan_results`**
([create_scan_results_table.php](../../app/database/migrations/2026_01_09_023519_create_scan_results_table.php))
to catch it. Result: duplicated findings, inflated counts, wrong compliance score.

### 2c. Double completion

`checkAndMarkRegionBasedScanComplete()` counts, compares, then updates status
([Scan.php:97-160](../../app/app/Models/Scan.php#L97)). Read-then-write again. Several
jobs can pass the threshold together and all mark the scan complete — each also re-running
findings evaluation for the non-region scan types.

### 2d. Partial-region data loss on retry

`hasAlreadyCollected()`
([ProcessRegionScanJob.php:168](../../app/app/Jobs/ProcessRegionScanJob.php#L168)) returns
true if *any* row exists for a (service, region). A job that fails halfway — a throttle
after 20 of 50 instances — retries, sees rows, skips collection entirely, and reports the
region done. Partial region data, `is_partial = false`.

Concurrency makes throttling more likely, so this gets worse exactly when we turn
concurrency on.

### 2e. No throttle handling

`createClient()` sets no `retries` config
([AwsSecurityScanner.php:67](../../app/app/Services/AwsSecurityScanner.php#L67)), so the
SDK uses legacy retry mode. The only backoff is the job-level 3× / 60s. Parallel workers
against one AWS account raise the odds of `RequestLimitExceeded` considerably.

---

## 3. What needs to change architecturally

Beyond the tactical fixes above, four structural changes:

**1. The completion counter is the wrong primitive.** The total is not known until
dispatch ends, so a mutable accumulator can never be safely read concurrently.

**Decision (Q1, 2026-08-01): use `Bus::batch()`.** `Bus::batch($jobs)->dispatch()` takes
the whole job list up front, so the size is fixed atomically at dispatch — the window that
2a exploits cannot exist. `finally()` fires exactly once when every job has settled, which
also removes the need for a separate atomic completion claim. This deletes
`expected_regions_count`, `processed_regions_count`, the `DISTINCT` counting query, and
reduces the stale-scan sweep to an orphaned-batch fallback.

Three things this pulls in:

- **The `job_batches` migration does not exist.** [config/queue.php:165](../../app/config/queue.php#L165)
  configures the table but it was never published. `php artisan make:queue-batches-table`
  is a prerequisite.
- **`->allowFailures()` is mandatory.** A batch is cancelled by default when any job
  fails. Today a failed region explicitly does *not* fail the scan
  ([ProcessRegionScanJob.php:177](../../app/app/Jobs/ProcessRegionScanJob.php#L177)); without
  `allowFailures()` one bad region would cancel the other 152.
- **Batch callbacks are serialized closures.** They cannot capture `$this` — capture
  `$scan->id` and re-load.

It also *improves* `is_partial`. Today a region job that exhausts its retries is never
counted, so the scan sits `running` until the 60-minute stale sweep marks it partial. With
a batch, `finally()` fires as soon as all jobs settle and `$batch->failedJobs > 0` gives an
accurate partial flag immediately, rather than an hour later.

Building one batch across all services also forces the plan-then-dispatch restructure
anyway, since `dispatchRegionScansForService()` currently dispatches per service.

**2. Global services should not run inside the orchestrator.**
`runGlobalScanForService` executes `iam` and `s3` inline, interleaved with region dispatch
([ProcessAuditScanJob.php:102](../../app/app/Jobs/ProcessAuditScanJob.php#L102)). S3 is
the bad one: `S3Scanner::executeApiCall` calls `determineBucketRegion()` — an uncached
`HeadBucket` — on every bucket-specific call, and there are five such methods. That is 10
API calls per bucket instead of 5, serial, blocking all 153 region dispatches behind it.
Make them jobs; the orchestrator becomes a pure planner that finishes in seconds.

**3. "Any rows exist" is too coarse a progress unit.** See 2d. Needs a per-(service,
region) work record with a real status, which also supplies the progress count for free.

**4. Client and credential lifecycle.** A new `Sdk()` and client is constructed for every
single API call ([GenericAwsScanner.php:82](../../app/app/Services/Scanners/GenericAwsScanner.php#L82)).
Memoize per (service, region), and set adaptive retry while we are there.

---

## 4. Work breakdown

**Priority:** P0 = must land before concurrency is switched on (silent-wrong-data risk);
P1 = the speed-up itself; P2 = worthwhile after; P3 = optional.
**Size** uses the repo's issue labels: XS under a day, S a day or two, M about a week.

| # | Task | Open questions | Dependencies / constraints | Pri | Size |
|---|---|---|---|---|---|
| T1 | Instrument scan phases (collect / findings / completion ms) and capture a baseline | Baseline against the test install, or a synthetic account? | None. Practices require measuring before optimising — re-prioritises everything below | P0 | XS |
| T2 | Replace `expected_regions_count` with `Bus::batch()` (Q1 resolved) | None | Blocks T3, T6, T8. Needs the `job_batches` migration published and `->allowFailures()` | P0 | M |
| T3 | Guard the completion write in the stale-scan sweep | None | Depends on T2. Shrunk by the batch decision: `finally()` handles the normal path, this covers only the orphaned-batch fallback | P0 | XS |
| T4 | Unique finding key + `insertOrIgnore` in `createFinding()` | Do existing installs already hold duplicates? Determines dedupe migration shape | **Demoted from P0 by Q2.** With evaluation running once in the batch callback there is no concurrent `createFinding`, so this is defence-in-depth (retried callback, sweep fallback) rather than a concurrency blocker. Needs a stored hash column — the columns exceed InnoDB's 3072-byte index limit. Schema and data migrations stay separate | P1 | S |
| T5 | AWS SDK adaptive retry on `createClient` | None | None. Cheap, but a genuine prerequisite — concurrency raises throttling odds | P0 | XS |
| T6 | Configurable worker concurrency: `numprocs`, separate region pool, fix the no-SQS `exec queue:work` path | None | **Depends on T2, T3, T5, T7.** Region pool defaults to 5 (Q3); audit pool stays small. ~60–120 MB + 1 MySQL connection per worker | P1 | XS |
| T7 | Remove findings evaluation from region jobs; run it once in the batch `finally()` (Q2 resolved) | None | Depends on T2. **Blocks T6** — until this lands, region jobs still evaluate concurrently and T4 becomes a hard prerequisite instead | P0 | S |
| T8 | Move `iam` / `s3` out of the orchestrator into their own jobs | Does this change `is_partial` semantics for global services? | Depends on T2 — completion accounting must cover them | P1 | S |
| T9 | Cache `determineBucketRegion()` per bucket in `S3Scanner` | None | None. Currently 10 API calls per bucket instead of 5 | P1 | XS |
| T10 | Per-(service, region) work record replacing `hasAlreadyCollected()` | New table vs. columns on an existing one? | **Not absorbed by T2.** A batch tracks counts, not which (service, region) failed, and does nothing about a half-collected region being recorded as done | P1 | S |
| T11 | Index `scan_details (scan_id, service, region)` | None | **Still wanted after T2.** The `DISTINCT` count goes away, but `hasAlreadyCollected()` queries exactly these three columns on every region job | P1 | XS |
| T12 | Chunk `ScanDetail` inserts instead of one `INSERT` per response | None | None | P2 | XS |
| T13 | Memoize AWS client construction per (service, region) | None | None. Currently `new Sdk()` per API call | P2 | XS |
| T14 | Remove `Log::debug` from the param-evaluation hot path | None | None. Only bites at `debug`, which DEBUG.md tells people to enable | P2 | XS |
| T15 | Remove dead `$scan->region` in `RulesEngine::getScanner()` | None | None — `scans` has no `region` column; works by accident | P3 | XS |
| T16 | Revisit region-pruning defaults | Is there a signal safer than the Tagging API that could make it default-on? | Deliberately off today for good reasons. Only worth it if T6+T7 fall short | P3 | S |
| T17 | Batch the dispatch loop (SQS `SendMessageBatch`) | None | Irrelevant on the `database` driver, which is what installs actually use | P3 | XS |

### Decisions needed before work starts

| Q | Question | Gates |
|---|---|---|
| ~~Q1~~ | ~~`Bus::batch()` or plan-then-dispatch?~~ **Resolved 2026-08-01: `Bus::batch()`** | T2, T3 |
| ~~Q2~~ | ~~Must findings appear incrementally during a running scan?~~ **Resolved 2026-08-01: no** | T4, T7 |
| ~~Q3~~ | ~~Default worker count?~~ **Resolved 2026-08-01: 5** (region pool) | T6 |
| Q4 | Do we commit to T8–T10 up front, or stop after T6 if T1 says we are fast enough? | Scope |

Q1 is the one to answer first — it is the largest structural fork and four tasks hang off it.

---

## 5. Explicitly not proposing

| Item | Why not |
|---|---|
| Redis + Horizon | Enterprise pattern, no enterprise problem — adds a service to every self-hosted install to do what `numprocs` does |
| Assume role once, pass credentials in the job payload | Writes temporary AWS credentials into the `jobs` table / SQS body; the ~300ms it saves mostly vanishes under parallelism |
| Async/promise-based SDK calls inside a job | Real complexity in the collection loop to win what process-level concurrency already gives |

---

## 6. Expected outcome

Unmeasured estimates, to be replaced by T1's numbers:

| Change | Expected effect |
| --- | --- |
| T2–T5 | No speed change. Unblocks T6; fixes latent silent-false-clean and duplicate-finding defects |
| T6 (5 workers) | Remaining fan-out time ÷ ~5 |
| T7–T9 | Removes the growing per-job cost and the orchestrator's inline S3 stall |

Plausible combined landing point is **1–2 minutes** for a full 11-service scan. That
number should not be committed to before T1.

---

## 7. Against the decision framework

1. **Simpler for the end user?** Yes — a scan that finishes in a minute instead of ten.
2. **Simpler to maintain?** Mostly yes. T6–T9 *remove* work and duplicated computation;
   Q1 = batch removes a whole counting subsystem. T4 adds a hash column and unique index —
   real added complexity, but it closes an existing correctness gap regardless of speed.
3. **Less complexity for the same outcome?** T6 alone (a config default) probably delivers
   most of the win for almost no code. That is why it is sequenced early and why T16–T17
   are optional.
4. **What are we willing to remove?** The whole-scan re-evaluation in every region job, the
   per-row insert loop, and — if Q1 = batch — `expected_regions_count`,
   `processed_regions_count` and most of the stale-scan sweep.
