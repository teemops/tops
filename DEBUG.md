# Debugging the Teemops Docker Stack

Operational debugging guide for the self-hosted Docker deployment: stuck scans, queue/worker
issues, viewing logs, and testing the account-linking/scan pipeline without waiting on full
CloudFormation or scan cycles.

For Playwright E2E test debugging, see [app/DEBUGGING.md](app/DEBUGGING.md) instead — this file
is about the running application, not the test suite.

---

## Where the logs are

`LOG_CHANNEL` defaults to `stderr` (see [docker-compose.yml](docker-compose.yml)), so application
logs go to the container's stdout/stderr, not `storage/logs/laravel.log`:

```bash
docker compose logs -f app
docker compose logs -f worker
```

**Filter to one scan** — every scan-related log line includes `scan_id`:

```bash
docker compose logs worker | grep "<scan_id>"
```

**Filter to one AWS account's onboarding** — SQS/SNS account-linking log lines include
`unique_id`/`external_id`/`account_id`:

```bash
docker compose logs worker | grep -i "aws account\|unique_id"
```

---

## Worker architecture

The `worker` container runs multiple processes under one supervisord
([worker-supervisord.conf](docker/app/worker-supervisord.conf)):

| Program | What it does |
|---|---|
| `tops-queue` | `queue:work database --queue=default,teemops_audit,teemops_audit_region` — processes **all scan jobs** (`ProcessAuditScanJob` orchestrator + `ProcessRegionScanJob` per-region), which run on the local database queue |
| `tops-account-queue` | `aws:process-sqs` — polls the `teemops_main` **SQS** queue for CloudFormation account-linking callbacks (the only thing that needs SQS) |
| `tops-scheduler` | `schedule:work` — runs Laravel's scheduled commands (currently: `scans:mark-stale-region-complete` every 5 min) |

**Queue architecture (single-server Docker):** scan processing runs on the **database** queue
(`SCAN_QUEUE_CONNECTION=database`, `SCAN_REGION_QUEUE_CONNECTION=database` in
`generated/teemops.env`), so the web tier needs no AWS credentials to enqueue a scan. **SQS is
used only for account linking** (SNS → `teemops_main` → `aws:process-sqs`). The `teemops_audit*`
SQS queues are still provisioned by the installer but sit idle. To move scans onto SQS, set those
two connections to `sqs-audit` / `sqs-audit-region` and re-add the corresponding `queue:work`
workers to [worker-supervisord.conf](docker/app/worker-supervisord.conf).

### Inspecting supervisord directly

`supervisorctl` run bare does **not** find the right config — the daemon was started with a
non-default path (`-c /etc/supervisor/worker-supervisord.conf`, see
[worker-entrypoint.sh](docker/app/worker-entrypoint.sh)), so you must pass `-c` explicitly:

```bash
docker compose exec worker supervisorctl -c /etc/supervisor/worker-supervisord.conf status
```

Tail one specific program's output live (useful for isolating one queue from the others'
interleaved logs):

```bash
docker compose exec worker supervisorctl -c /etc/supervisor/worker-supervisord.conf tail -f tops-scan-region-queue
```

If `supervisorctl status` shows every program `RUNNING` but a specific queue never logs any
activity for a scan you just started, the issue is upstream (nothing dispatched, or nothing
delivered) — see [Diagnosing a stuck scan](#diagnosing-a-stuck-scan-running-forever) below.

---

## Diagnosing a stuck scan ("Running" forever)

### Why this happens

Region-based scans (EC2, RDS) dispatch one `ProcessRegionScanJob` per AWS region. The scan only
flips to `completed` once every dispatched region job calls back in
(`Scan::checkAndMarkRegionBasedScanComplete()`). If **any single region job** permanently fails
(exhausts its 3 retries), that region is never counted, the expected total is never reached, and —
without the scheduler safety net — nothing else re-checks it.

**Zero resources in a region is not the cause.** Each region job writes at least one `ScanDetail`
row for its `(region, service)` pair regardless of whether anything was found — `storeScanDetail()`
runs unconditionally for the main API call in `RulesEngine::executeTask()`. An account with zero
RDS instances (or zero EC2 instances in a given region) still counts that region as processed.
If a scan is stuck, something is preventing the region job from running or reporting back at all —
not the absence of resources.

**Dispatch is now per-region and self-correcting.** `ProcessAuditScanJob::dispatchRegionScansForService()`
dispatches each region job explicitly (via `Bus::dispatch()`, not the deferred `Job::dispatch()`
fluent call) inside its own try/catch. `expected_regions_count` only counts jobs that were
*actually* confirmed dispatched — not the full region list up front — so a single region failing to
enqueue can no longer permanently block completion. Check the `{service} scan dispatched to
regions` log line for `dispatched` vs `regions_count` and a `failed_regions` list — a gap between
them now means the dispatch itself failed for those regions (logged individually as `Failed to
dispatch region scan job`), not a silent, invisible failure.

### Step 1 — check the scan's own record

```bash
docker compose exec app php artisan tinker --execute="
\$scan = App\Models\Scan::find('<scan_id>');
echo 'status: ' . \$scan->status . PHP_EOL;
echo 'expected_regions_count: ' . \$scan->expected_regions_count . PHP_EOL;
echo 'processed (region,service) pairs: ' . \$scan->details()->whereNotNull('region')->select('region','service')->distinct()->get()->count() . PHP_EOL;
"
```

If `processed` is less than `expected_regions_count` and has stopped climbing, one or more region
jobs never completed.

### Step 2 — force the completion check now

Don't wait for the 5-minute scheduler tick:

```bash
docker compose exec app php artisan scans:mark-stale-region-complete --minutes=0 --dry-run
docker compose exec app php artisan scans:mark-stale-region-complete --minutes=0
```

### Step 3 — check whether the region-job queue is actually being consumed

```bash
docker compose exec worker supervisorctl -c /etc/supervisor/worker-supervisord.conf status
docker compose exec worker supervisorctl -c /etc/supervisor/worker-supervisord.conf tail -f tops-scan-region-queue
```

Start a new scan and watch this tail live. If nothing appears at all, jobs either never reached
SQS or aren't being delivered — go to Step 4.

### Step 3b — confirm which queue connection is *actually* in effect

If the dispatch log shows `dispatched: N` with an empty `failed_regions` but the region worker
never picks anything up, the jobs went somewhere other than where the worker is listening. Check
what the containers actually resolved (not what you think `.env` says):

```bash
docker compose exec worker printenv | grep -E "QUEUE_CONNECTION|SCAN_"
docker compose exec app printenv | grep -E "QUEUE_CONNECTION|SCAN_"
```

`SCAN_REGION_QUEUE_CONNECTION` should be `database` (scans run on the database queue; see the
queue architecture note above). Both app and worker must agree. Confirm with:

```bash
docker compose exec app php artisan tinker --execute="
echo 'scan_connection: ' . config('queue.scan_connection') . PHP_EOL;
echo 'scan_region_connection: ' . config('queue.scan_region_connection') . PHP_EOL;
"
```

And check whether jobs are piling up in the database queue:

```bash
docker compose exec app php artisan tinker --execute="
foreach (DB::table('jobs')->select('queue', DB::raw('count(*) as n'))->groupBy('queue')->get() as \$r) {
    echo \$r->queue . ': ' . \$r->n . PHP_EOL;
}
"
```

> Compose gotcha: an `environment:` entry **overrides** `env_file:`. Adding
> `SCAN_REGION_QUEUE_CONNECTION` to `environment:` will silently override the value
> `generated/teemops.env` supplies — this exact mistake caused region jobs to be enqueued to the
> database while workers polled SQS.

### Step 4 — check the actual SQS queue depth

```bash
aws sqs get-queue-url --queue-name teemops_audit_region --region <TOPS_DEPLOYMENT_REGION>
aws sqs get-queue-attributes --region <TOPS_DEPLOYMENT_REGION> --queue-url <URL> \
  --attribute-names ApproximateNumberOfMessages ApproximateNumberOfMessagesNotVisible
```

- Messages piling up and not shrinking → worker isn't consuming (check Step 3 again, check AWS
  credentials mounted at `/var/www/.aws`).
- Both stay at zero even right after dispatch → the dispatch loop isn't actually sending
  anything — see the isolated dispatch test below.
- Also check the dead-letter queue (`teemops_audit_dlq`, `teemops_main_dlq`) — a permanently
  failing job ends up there after 5 receive attempts and won't come back on its own.

### Step 5 — isolate the SQS plumbing from the scan-dispatch loop

This proves whether `sqs-audit-region` itself works, independent of anything scan-specific:

```bash
docker compose exec worker supervisorctl -c /etc/supervisor/worker-supervisord.conf tail -f tops-scan-region-queue
```

...then in another shell:

```bash
docker compose exec app php artisan tinker --execute="
App\Jobs\ProcessRegionScanJob::dispatch(App\Models\Scan::latest()->first(), '<region>', 'rds')
    ->onConnection('sqs-audit-region');
echo 'dispatched';
"
```

If this shows up in the tail (even if it then errors on scan-state validation — that's fine, we're
only checking delivery), the connection/credentials/queue-name plumbing works, and the bug is
specific to the real dispatch loop (`ProcessAuditScanJob::dispatchRegionScansForService()`). If it
never shows up, the `sqs-audit-region` connection itself is broken.

---

## Testing account-linking without a full CloudFormation cycle

Launching a real child-account CloudFormation stack takes 10-15 minutes to fail/roll back if
anything's wrong upstream. `aws:test-callback` injects a synthetic
`TopsCustomNotifier` message — the same payload CloudFormation's custom resource sends — directly
into SNS or SQS, so you can test the whole chain (or just the worker) in seconds.

**Full chain (SNS topic → SQS → worker), with pass/fail assertion:**

```bash
docker compose exec app php artisan aws:test-callback --target=sns --create-account --wait=30
```

**Worker-only (bypass SNS, inject straight into SQS)** — if the full chain fails but this passes,
the break is upstream of the queue (SNS subscription, topic policy, region):

```bash
docker compose exec app php artisan aws:test-callback --target=sqs --create-account --wait=30
```

**Bare "is the worker reading the queue at all" check** (no DB match needed — just confirms
consumption):

```bash
docker compose exec app php artisan aws:test-callback --target=sqs --wait=0
docker compose logs -f worker | grep -i "account not found"
```

See `php artisan aws:test-callback --help` for `--request-type=Delete|Update`, targeting a
specific org/account, and a custom `--response-url`.

---

## Common issues we've hit (and what fixed them)

| Symptom | Cause | Fix |
|---|---|---|
| Worker: `No application encryption key has been specified` on any `AwsAccount` write | `APP_KEY` wasn't passed to the `worker` container (only `app` self-generated one) | Set `APP_KEY` once in root `.env` (shared by both containers) — see [.env.docker.example](.env.docker.example) |
| S3 scan: `AuthorizationHeaderMalformed ... expecting 'us-west-2'` | `getBucketRegion()` used `GetBucketLocation` with a client pinned to the default region — fails for any bucket outside it | Fixed: uses `$s3Client->determineBucketRegion()` instead ([S3Scanner.php](app/app/Services/Scanners/S3Scanner.php)) |
| S3 scan: `[Bucket] is missing and is a required parameter` for `getPublicAccessBlock` only | `tasks.json`'s file-level `config.defaults` was never merged into per-task config, so param resolution fell back to name-guessing (which only matches actions containing "Bucket") | Fixed in `RulesEngine::executeScan()` |
| Account-linking: CloudFormation never completes, SQS message never seen | SNS→SQS subscription pointed at the wrong region (a parameter-passing bug in `install.sh`) | Fixed in [sns.topic.cfn.yaml](infra/cloud-stack/stackset/sns.topic.cfn.yaml) — endpoint now derives region from `AWS::Region`, not a passed parameter. Re-run `./install.sh` to update the deployed stack. |
| Worker: `PDOException [2002] Connection refused` right after startup | Worker started polling before MySQL was reachable | Entrypoint waits for MySQL, but if it recurs, check `docker compose ps` / MySQL health |
| Region-based scan (EC2/RDS) stuck at `status: running` forever | No periodic re-check existed for scans where a region job permanently failed | Fixed: `scans:mark-stale-region-complete` now runs every 5 minutes via the scheduler (`tops-scheduler`) |
| Region-based scan stuck even with the scheduler running; `expected_regions_count` can never be reached | Dispatch loop counted the *intended* region total up front, not confirmed deliveries — any silent per-region dispatch failure permanently inflated the expected count | Fixed: dispatch is now per-region with its own try/catch; `expected_regions_count` only counts actually-dispatched jobs ([ProcessAuditScanJob.php](app/app/Jobs/ProcessAuditScanJob.php)) |
| Log says `dispatched: 17, failed_regions: []` but `tops-scan-region-queue` never processes anything | **Queue mismatch.** Compose's `environment:` block overrides `env_file:`, so `SCAN_REGION_QUEUE_CONNECTION: ${...:-database}` (interpolated from root `.env`) clobbered the `sqs-audit-region` value from `generated/teemops.env`. Jobs went to the **database** queue `teemops_audit_region` while the worker polled **SQS** | Fixed: those vars removed from `environment:` in [docker-compose.yml](docker-compose.yml) so env_file layering wins (`.env` → `generated/teemops.env`) |
| Region jobs enqueued but never consumed in no-AWS (Phase 1 / `database`) mode | The database worker ran `queue:work database` with no `--queue`, so it only consumed `default` — region jobs are pushed to `teemops_audit_region` | Fixed: database workers now listen on `default,teemops_audit,teemops_audit_region` |
| Scan with zero RDS/EC2 resources appears stuck | Misdiagnosis — zero resources in a region still writes a `ScanDetail` row and counts as processed; this isn't the actual cause of a stuck scan | See [Diagnosing a stuck scan](#diagnosing-a-stuck-scan-running-forever) above |
| All regions finish, findings/results are in the DB, but scan never leaves `running`/`pending` | **`expected_regions_count` was inflated.** It accumulated (`current + dispatched`) on every `ProcessAuditScanJob` run, but `processed` counts *distinct* `(region, service)` pairs — capped at regions × region-based types. Any duplicate run of the orchestrator (SQS at-least-once redelivery, a retry, a replayed stale job) made the target permanently unreachable | Fixed: `expected_regions_count` is reset at the start of each run so re-runs converge ([ProcessAuditScanJob.php](app/app/Jobs/ProcessAuditScanJob.php)) |
| Scan stuck in `pending` specifically; even `scans:mark-stale-region-complete` won't touch it | Both `Scan::checkAndMarkRegionBasedScanComplete()` and the sweep command only considered `status === 'running'`, so a scan whose orchestrator died before flipping it to `running` was invisible to every completion path | Fixed: both now treat `pending` and `running` as eligible (non-terminal) |
| Creating a scan in the UI fails: "could not be queued for processing"; app log shows `Error retrieving credentials from the instance profile metadata service ... 169.254.169.254` | **php-fpm clears the environment.** `clear_env` defaults to `yes`, so web requests didn't see `AWS_SHARED_CREDENTIALS_FILE`/`AWS_PROFILE` (only in compose `environment:`), and the AWS SDK fell back to EC2 IMDS, which times out off-EC2. CLI (workers, `tinker`, scheduler) was unaffected — hence config checks looked fine | Fixed: `clear_env = no` pool override ([php-fpm-clear-env.conf](docker/app/php-fpm-clear-env.conf)) so web requests inherit the container env like CLI |
| `supervisorctl status` → `Error: .ini file does not include supervisorctl section` | Bare `supervisorctl` doesn't find the custom config path the daemon was started with | Always pass `-c /etc/supervisor/worker-supervisord.conf` explicitly |

---

## Quick reference

```bash
# Live logs
docker compose logs -f app
docker compose logs -f worker

# Per-scan logs
docker compose logs worker | grep "<scan_id>"

# Worker process health
docker compose exec worker supervisorctl -c /etc/supervisor/worker-supervisord.conf status

# Unstick a region-based scan right now
docker compose exec app php artisan scans:mark-stale-region-complete --minutes=0

# Test account-linking without CloudFormation
docker compose exec app php artisan aws:test-callback --target=sns --create-account --wait=30

# Inspect a scan's progress directly
docker compose exec app php artisan tinker --execute="
\$scan = App\Models\Scan::find('<scan_id>');
echo \$scan->status . ' | expected=' . \$scan->expected_regions_count . ' | started=' . \$scan->started_at . PHP_EOL;
"
```
