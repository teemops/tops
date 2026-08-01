# Scan Detail — Summarise and Dispatch - User Story

**Roadmap:** S-1 · **Issue:** [#86](https://github.com/teemops/tops/issues/86) ·
**Milestone:** Findings & Scans v2 · **Status:** ✅ **Shipped 2026-08-02** · **Raised:** 2026-08-02

> **Verified 2026-08-02** by Ben on the reference install. One bug found and fixed in review:
> the scan group rendered `[object Object]` because `ScanProfilesService::rulesetLabels()`
> returns a `['label' => ..., 'description' => ...]` per ruleset and the whole map was being
> shipped to the client typed as flat strings. Labels are now resolved server-side, so the
> page receives only the labels for the rulesets this scan actually ran.
>
> **Known gap, deliberate:** "Fix these first" entries do not link into Findings. A theme
> spanning several rules cannot be expressed as a filter while Findings is single-select on
> `finding_type`, and making only the single-rule entries clickable would be worse than making
> none of them clickable. Needs multi-select or F-4's data model first.

**Depends on** (all landed): D-1 durable findings ([#81](https://github.com/teemops/tops/issues/81)) ·
F-7 URL filters ([#83](https://github.com/teemops/tops/issues/83)) ·
F-3 service filter ([#85](https://github.com/teemops/tops/issues/85)) ·
F-1 remediation ([#82](https://github.com/teemops/tops/issues/82))

> **F-1 had to land first and did.** This story deletes the per-finding list from Scan detail,
> which makes Findings the only page that renders remediation at all. Doing it while F-1 was
> broken would have hidden remediation for the 46 medium/low rules that carry only a one-liner.

## User Story

As a solo engineer opening a finished scan, I want a summary that tells me what to fix first
and sends me to the right place to fix it, so that I stop reading the same finding twice in
two different pages that disagree with each other.

## Expected Behavior

**Scan detail summarises and dispatches. Findings is where a finding is read.** Every number
on Scan detail is a link into Findings, filtered. Nothing is written twice.

Opening the **most recent completed scan** for an account shows:

- **Run facts** — what ran (the benchmark, or the services selected), when, how long, its
  status, and which account
- **Severity totals** for what is open now
- **Fix these first** — the changes that clear the most, ranked by severity rather than raw
  count, so eighteen mediums do not outrank eighteen highs. One entry can span several rules,
  because one real fix often does
- **Break down by service or finding type** — every row a link into Findings, already filtered

Opening **any older scan** shows the run facts and nothing else, plus a link to the current
scan. This is deliberate and is the direct consequence of D-11: findings are current state, so
a breakdown printed under a historical date would be **wrong and plausible at the same time**,
which is worse than the duplication this story removes.

**The per-finding list comes off Scan detail entirely** — no titles, no resource ids, no
remediation, no severity filter. That deletion is what pays for the feature.

## User Acceptance Criteria

**Run facts**

- [x] Given any scan, when I open it, then I see its scan group, when it ran, its status and
      its account
- [x] Given a scan that ran a whole benchmark, when I read the scan group, then it names the
      benchmark; given a scan narrowed to services, then it says how many services ran
- [x] Given a partial scan, when I view it, then I am told the counts are a floor rather than
      a total, because a service unscanned in a region is not a service with nothing wrong

**The latest scan**

- [x] Given the most recent completed scan for an account, when I open it, then I see severity
      totals and the breakdown
- [x] Given the breakdown, when I read its heading, then it says these are findings open now
      rather than what this scan personally saw
- [x] Given "Fix these first", when it ranks entries, then a higher-severity group outranks a
      larger but less severe one
- [x] Given a service row, when I click it, then I land on Findings filtered to that service
- [x] Given a finding-type row, when I click it, then I land on Findings filtered to that type

**Older scans**

- [x] Given a scan that is not the latest completed one for its account, when I open it, then
      I see the run facts, a note that it has been superseded, and a link to the latest scan
- [x] Given that same older scan, when I look for totals or a breakdown, then there are none

**Removal**

- [x] Given any scan, when I open it, then there is no per-finding list, no remediation text
      and no severity filter on the page

**Authorization and isolation**

- [x] Given a scan belonging to another organization, when I request it, then it is not found
- [x] Given the breakdown and totals, when they are computed, then they include only findings
      from the caller's organization

## Technical Notes

**The breakdown is current state, not scan state.** It is computed for the scan's **AWS
account**, not from the scan's own rows. That is what D-11 made true, and it is why the
breakdown appears on the latest scan only.

**"Latest completed scan for this account"** is the one query this story really turns on. A
scan shows a breakdown when no *completed* scan for the same `aws_account_id` has a later
`completed_at`. Scope it by organization like everything else.

**Build the breakdown as a reusable component.** F-5 ([#88](https://github.com/teemops/tops/issues/88))
and F-6 ([#89](https://github.com/teemops/tops/issues/89)) are the same grouped,
severity-stacked, drillable block keyed on the organization instead of one account. If it does
not come out shared, the Insights work doubles. **This is the one architectural instruction in
this story.**

**Reuse the existing severity weighting** — critical 10, high 5, medium 2, low 1 — which the
security score already uses. Ranking "Fix these first" by a second, different weighting would
make two numbers on the same product disagree about what "worse" means.

**"Fix these first" groups by `tips.json` themes**, which already map 36 rules into 17 themes.
Use that mapping; do not invent a second one.

**`show` does not currently return `scan_types` or `rulesets`** even though both are stored.
Adding them to the payload is most of the run-facts work.

**`is_partial` is already computed and returned**, and matters more here than anywhere else:
summarising by service invites "EC2 is clean" when EC2 was never enumerated in three regions.
Note that PERF-2 ([#65](https://github.com/teemops/tops/issues/65)) is an open bug in which
`is_partial` can read `false` after regions silently failed — this story surfaces the flag
honestly but cannot fix its accuracy.

**Drill-through is a plain `?service=` or `?finding_type=` link.** No scan chip, no `?scan_id=`
— the wireframes dropped both when D-11 made findings current state. F-7 and F-3 already
handle the destination.

## Out of Scope

- **Insights** (F-5, F-6) — the same component, keyed on the organization; separate stories
- **Breakdown by benchmark** — needs F-4's data-model change, since nothing records a
  finding's benchmark yet
- **Any per-scan history or trend** — explicitly rejected by D-11; revisit only if a design
  partner asks, and then as a new data model rather than an addition
- **A `?scan_id=` filter or "from scan …" chip** — dropped by the wireframes; recorded so it
  is not re-proposed
- **Fixing `is_partial`'s accuracy** — that is PERF-2
- **Re-run this scan** — the wireframe shows it, but it is a separate capability; not built here

## Success Metrics

- A finding's title, resource and remediation appear in exactly one place in the product
- From opening a scan, reaching the filtered list of one service's findings is one click
- Opening an older scan cannot show a number that contradicts the Findings page
- F-5 consumes the breakdown component without modifying it

## Related Practices

- [Product Practices](../practices/product.md) — the deletion is the feature; resist adding
  back what was removed
- [Architecture Practices](../practices/architecture.md) — one shared breakdown component,
  clear boundary between summarising and reading
- [Security Practices](../practices/security.md) — the scan, its totals and its breakdown are
  all tenant data and scoped by `organization_id`; cross-organization tests ship with it
- [Database Practices](../practices/database.md) — grouped aggregate queries against indexed
  columns, no N+1 across services
- [Testing Practices](../practices/testing.md) — feature tests for latest-vs-older behaviour,
  the ranking, and cross-organization rejection
