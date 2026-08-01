# Durable Findings - User Story

**Roadmap:** D-1 · **Status:** draft, awaiting sign-off · **Raised:** 2026-08-01

> **This is the gate.** No code until this is agreed. All four open points were settled
> with Ben on 2026-08-01; the reasoning for each is in place below. What remains open is
> named in "Still to settle during design".

> **Scope cut, 2026-08-01, after [#92](https://github.com/teemops/tops/pull/92) landed.**
> `resource_gone` is **deferred to a follow-up** and is not built here. Everything else
> ships. The reasoning is in "Why `resource_gone` waits" below; the short version is that
> its safety guard rests on `is_partial`, and #92's design demonstrates `is_partial` reads
> `false` when regions silently failed to report.

## User Story

As a solo engineer scanning my AWS account repeatedly, I want each finding to be one
lasting record of one problem on one resource, so that scanning again tells me what
changed instead of telling me everything twice.

## Expected Behavior

A finding is identified by **AWS account + resource + rule**. Scanning the same account
again does not create a second copy of a problem that is still there — it updates the one
that exists.

The rule that governs everything else: **the most recent scan that actually examined a
resource is authoritative for that finding's content. Status belongs to the user.** A scan
may change a finding's severity, title or remediation; only a person marks something
ignored, and only a person's judgement is overridden by evidence.

**Not looking is not the same as looking and seeing nothing.** Three outcomes, not two:

| What the scan did | Outcome |
| --- | --- |
| Examined the resource, rule now passes | **Resolved — fixed** |
| Enumerated that service in that region, and the resource was not there | **Resolved — resource gone** |
| Did not enumerate that service in that region at all | **Untouched.** Not resolved, not fixed |

The third row is the safety rule, and it is what makes per-service scans (F-2) and partial
scans safe: scanning only S3 must never resolve an EC2 finding, and a region that failed to
report must never resolve the findings inside it. The second row is what stops deleted
resources haunting the list forever.

The visible outcome: the Findings page and Insights show what is true **now**, not the sum
of every scan ever run.

## User Acceptance Criteria

**The core lifecycle**

- [ ] Given a finding for a resource and rule, when a later scan examines that resource and
      the rule still fails, then there is still exactly one finding — its `last_seen_at` and
      `last_seen_scan_id` advance, and no second row is created
- [ ] Given a finding, when a later scan examines that resource and produces different
      content for the same rule (severity, title, description, remediation), then the finding
      shows the newer content
- [ ] Given a finding, when a later scan examines that resource and the rule now passes, then
      the finding is **resolved with reason `fixed`**, timestamped, and the scan recorded
- [ ] ~~Given a finding, when a later scan **successfully enumerates that service in that
      resource's region** and the resource is absent, then the finding is **resolved with
      reason `resource_gone`**~~ — **deferred, see "Why `resource_gone` waits"**
- [ ] Given a finding, when a later scan does not enumerate that service in that region at
      all, then the finding is unchanged — not resolved, not touched, still in the list
- [ ] Given a resolved finding, when I look at it, then I can tell **which** of the two
      reasons resolved it — "no longer failing" and "resource no longer exists" are not the
      same news

**Not looking is not seeing nothing** — the cases that make auto-resolve safe

- [ ] Given findings across several services, when I run a scan of one service only (F-2),
      then findings for every other service are untouched — not resolved as gone
- [ ] Given a scan marked partial because regions failed, when it completes, then no finding
      in an unreported region is resolved for any reason
- [ ] Given a scan that fails to enumerate a service because permission was denied, when it
      completes, then findings for that service are untouched — an `AccessDenied` must never
      read as "the resource is gone"

**Status belongs to the user**

- [ ] Given a finding I marked **ignored**, when a later scan finds it still failing, then it
      stays ignored — it does not reappear as open
- [ ] Given a finding I marked **resolved**, when a later scan finds it still failing, then it
      is **reopened** — the evidence contradicts the claim, and that contradiction is the most
      useful thing a scanner can tell me
- [ ] Given a finding I marked **ignored**, when a later scan finds it compliant, then it is
      recorded as resolved and leaves the open list

**What the user actually sees**

- [ ] Given one scan producing 67 findings, when I scan the same account again having fixed
      nothing, then Findings still shows 67 — not 134
- [ ] Given I fixed something between scans, when the second scan completes, then that finding
      is no longer in my open list and the count has dropped
- [ ] Given a finding, when I look at it, then I can see when it was first seen and when it
      was last seen
- [ ] Given the most recent completed scan for an account, when I open it, then I see the
      findings breakdown for that account
- [ ] Given an **older** scan for that account, when I open it, then I see the run facts and a
      link to the current scan, and **no breakdown or severity totals** — current-state numbers
      under a historical date would be a lie

**Tenancy and authorization** — non-negotiable per `docs/practices/security.md`

- [ ] Given a finding in another organization, when I request it by id, then it is not found
- [ ] Given two organizations with the same AWS account id, when either scans, then neither
      organization's findings are merged with or visible to the other
- [ ] Given the upsert path, when it matches an existing finding, then the match is scoped by
      `organization_id` — a finding is never updated across an organization boundary

## Technical Notes

**Identity.** `aws_account_id + service + resource_type + resource_id + finding_type`,
scoped by `organization_id`. `scan_results` today has no `aws_account_id` — it is only
reachable by joining `scans` — so the account moves onto the finding as part of this work.

**All three outcomes are already derivable.** `scan_details` records every resource a scan
examined — `service`, `api_method`, `resource_id`, `region` — keyed `(scan_id, service,
resource_id)` and indexed. `FindingsEngine` walks those details and raises a finding only
when the condition fails. So:

| Test against this scan's `scan_details` | Outcome |
| --- | --- |
| A detail exists for the same service + `api_method` + `resource_id`, and no finding was raised | `fixed` |
| Details exist for that service + `api_method` **in that region**, but none for this `resource_id` | `resource_gone` — **deferred** |
| No details for that service + `api_method` in that region | untouched |

**The middle row is the dangerous one and needs a guard.** Concluding "gone" from an empty
result is only sound when the enumeration actually succeeded. A failed API call, an
`AccessDenied`, or a region that never reported all produce the same empty set as a deleted
resource — and a false `resource_gone` is the worst failure this design can produce, because
it silently tells someone a security problem went away. Guards:

- Never conclude `resource_gone` from a scan where `is_partial` is true, for any region that
  did not report
- Match on `scan_details.region`, so a resource in an unscanned region is never judged absent
- Require evidence the enumeration ran — details present for that service and `api_method` —
  rather than inferring it from the scan having completed

**Resolution reason, not a second status.** The existing `status` enum (`open`, `resolved`,
`ignored`) is unchanged; a nullable `resolution_reason` (`fixed`, `resource_gone`, `manual`)
records why. That lets the UI say "resolved — resource no longer exists", which is a
materially different claim from "resolved — no longer failing" and should not be flattened
into one word.

**Account-level rules** (`getAccountSummary`, e.g. `cis-1.4`) have no meaningful
`resource_id`. They need a stable synthetic identity or they will churn between `open` and
`resource_gone` on every scan — settle during design, not implementation.

**Write path.** `FindingsEngine::createFinding` currently inserts per scan and dedupes only
`where('scan_id', $scan->id)` — that single clause is why findings accumulate. It becomes an
upsert against the durable key, plus a reconciliation pass at the end of a scan that closes
what was examined and found compliant.

**Schema.** Lifecycle columns (`first_seen_at`, `last_seen_at`, `last_seen_scan_id`,
`fixed_at`, `aws_account_id`, `organization_id`) plus a unique index on the identity above.
Migration must be reversible per `docs/practices/database.md`.

**Existing data migrates cleanly.** The reference install has 67 findings from a single
completed scan, so there is nothing to collapse — first and last seen both point at that
scan. Installs with several scans of one account will collapse duplicates, which is the
point. This is a data migration and ships separately from the schema migration.

**DECIDED 2026-08-01 — no per-scan history, and no observations table.** Findings are
current state, so "what did scan X find" is not answerable for any scan but the latest, and
we are not building the findings/observations pair that would make it answerable.

The consequence is a rule for S-1, not a caveat: **the breakdown appears only on the most
recent completed scan for an account.** Open an older scan and you get the run facts — scan
group, time, status, account, whether it was partial — and a link to the current one. You do
not get a breakdown, because any breakdown shown there would be current state wearing an old
scan's date, which is exactly the misreading this decision exists to prevent.

The severity totals inherit the same rule for the same reason: they are counts of findings
that exist *now*, so they belong only on the scan that produced the current picture.

## Out of Scope

Deliberately excluded, so v1 stays shippable:

- **Benchmark on the finding** (F-4) — separate item, though it attaches to the durable
  finding once this lands, which is why this comes first
- **All UI work** — S-1, F-3, F-5 consume this; none is part of it
- **Notifying anyone** when a new finding appears or a finding is fixed
- **Trend history and charts** — "findings over time" needs an observations table, which was
  decided against on 2026-08-01. Revisit only if a design partner asks for trends, and treat
  it as a new data model rather than an addition to this one
- **Auto-closing on absence** — explicitly rejected above; it would be broken by F-2

## Why `resource_gone` waits

**Decided 2026-08-01, after reading [#92](https://github.com/teemops/tops/pull/92)'s design
for parallel region scans.** The two auto-resolve reasons rest on different kinds of
evidence, and only one of them is safe on today's scan accounting:

| | Evidence | Safe now? |
| --- | --- | --- |
| `fixed` | **Positive** — a `scan_detail` exists for *that resource*, and no finding was raised | **Yes.** Nothing can fabricate a detail row for a resource that was not examined |
| `resource_gone` | **Inference from absence** | **No** — see below |

#92 documents two ways a scan reports more coverage than it achieved, and both were
verified against the code rather than taken on the document's word:

1. **The completion counter races.** `expected_regions_count` accumulates *during*
   orchestration, so a region job finishing inside that window compares `1 >= 0` and writes
   `status = completed, is_partial = false` with a fraction of the region-service pairs
   scanned. Masked today only by single-worker timing.
2. **A half-collected region reports as done.** `ProcessRegionScanJob::hasAlreadyCollected()`
   returns true when *any* detail row exists for a (service, region), so a job that throttles
   part-way through retries, sees rows, skips collection, and reports the region complete.

Guard 1 in the technical notes — never conclude gone from a partial scan — is therefore
resting on a flag that reads `false` in exactly the cases it needs to catch. Requiring
positive `scan_detail` evidence survives the first bug, because a region that never ran
leaves no details at all. It does **not** survive the second: a half-collected region has
details, so its uncollected resources are indistinguishable from deleted ones.

Shipping it anyway would auto-resolve findings for resources nobody looked at — the single
failure this design set out to prevent.

**What ships instead:** durable identity, the upsert, status preservation, and `fixed`.
That is the whole headline outcome — counts stop doubling, fixed problems leave the list,
and `ignored` survives a rescan. Ghost findings from deleted resources stay open, which is
the conservative behaviour Ben originally specified anyway, and `last_seen_at` makes them
identifiable in the meantime.

**What unblocks it:** trustworthy per-(service, region) collection accounting — T10 in #92's
design. Add `resource_gone` then, against the criteria already written above.

**One thing this work gives #92 back:** the unique index on the finding identity makes
#92's finding 2b — concurrent region jobs double-inserting the same finding, with no unique
constraint to stop them — impossible at the database level.

## Still to settle during design

Not blocking sign-off, but each needs an answer before the code is written:

1. **Identity for account-level rules** — see the technical note above
2. **How long a resolved finding stays visible.** It leaves the open list immediately; does
   it stay readable indefinitely, or age out? Nothing deletes findings in this design
3. **Whether `resource_gone` should be reversible.** A resource that reappears with the same
   id — an instance restored from a snapshot, a bucket recreated — currently reopens the
   finding, which is probably right but has not been thought about

## Success Metrics

- **Scanning twice with nothing fixed does not change the finding count.** Today it doubles
  it. This is the single number that says whether this worked
- A fixed problem leaves the open list on the next scan, without anyone clicking anything
- `ignored` survives a rescan — measurable on the reference install, which has exactly one
  ignored finding today and will resurrect it as open on its next scan
- Insights' remediation rate reflects real remediation rather than manual status edits

## Related Practices

- [Product Practices](../practices/product.md) — simplicity first; the four questions. This
  removes a concept (per-scan result rows) rather than adding one
- [Database Practices](../practices/database.md) — schema design, reversible migrations,
  data migrations kept separate
- [Security Practices](../practices/security.md) — multi-tenant isolation; the upsert match
  must be scoped by `organization_id`
- [Testing Practices](../practices/testing.md) — the current suite has no multi-scan
  coverage at all, which is why this went unnoticed
