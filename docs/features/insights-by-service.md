# Insights — Group by Service with Severity - User Story

**Roadmap:** F-5 · **Issue:** [#88](https://github.com/teemops/tops/issues/88) ·
**Milestone:** Findings & Scans v2 · **Status:** ✅ **Shipped 2026-08-02** · **Raised:** 2026-08-02

> **Verified 2026-08-02** by Ben on the reference install.
>
> **The architectural bet paid off, measurably.** `FindingsBreakdown.vue` and
> `FindingsBreakdownService` were consumed by a second page **byte-for-byte unchanged** — the
> whole feature is ~20 lines of production code plus tests. That was the one instruction S-1
> carried, and this story was the test of it.

**Depends on** (both landed): S-1's shared breakdown ([#86](https://github.com/teemops/tops/issues/86)) ·
D-1 durable findings ([#81](https://github.com/teemops/tops/issues/81))

> **This is the story that tests S-1's architecture.** The breakdown was built as a service
> taking an organization and an *optional* account, and as a component taking tabs and emitting
> selections, specifically so this page is a re-key rather than a rewrite. If this story needs
> either of them changed, the instruction in S-1 was not followed properly.

## User Story

As a solo engineer looking at Insights, I want the services listed with a severity breakdown
rather than a bare count, so that I can tell whether "12 findings" is twelve Low or three
Critical before deciding where to spend my afternoon.

## Expected Behavior

"Top affected services" — currently a service name and a number — becomes the same
severity-stacked, drillable breakdown that Scan detail shows, keyed on the whole organization
instead of one account. Every row links into Findings, filtered to that service.

It carries the same Service / Finding type toggle, because it is the same component.

**The breakdown reads current state, across all scans, and is not affected by the period
selector.** The rest of Insights — the trend chart, the remediation rate, the scan count — is
genuinely about a window of time and stays that way. This block is not, and says so.

## Why the breakdown ignores the period

This is the one real decision in the story, so it is written down rather than assumed.

The whole point of this workstream is that a number means the same thing wherever you read it.
Findings and Scan detail both show **what is open now**. If Insights windowed the same
breakdown to 30 days, EC2 would read 60 on Scan detail and 12 on Insights, and we would have
rebuilt the exact problem — "the same thing looks different depending on which page you
opened" — that S-1 just finished deleting.

There is a second reason. Since D-1 a finding is durable, so `created_at` is *when the problem
was first raised*, not when it was last seen. Windowing on it answers "problems first noticed
in the last 30 days", which is a legitimate question and **not** the one "findings by service"
appears to answer.

The block is therefore labelled **across all scans**, so nobody reads it as period-scoped.

## User Acceptance Criteria

- [x] Given Insights, when it loads, then findings are grouped by service with a severity
      breakdown on each row rather than a bare count
- [x] Given a service row, when I click it, then I land on Findings filtered to that service,
      across all scans
- [x] Given the toggle, when I switch to Finding type, then the same rows are grouped by rule
      instead, and clicking one filters Findings by that type
- [x] Given an organization with no findings, when Insights loads, then an empty state appears
      rather than an empty chart
- [x] Given the period selector is changed, when the breakdown re-renders, then its numbers do
      not change — it is current state, and is labelled as such
- [x] Given another organization's findings, when the breakdown is computed, then none of them
      are counted
- [x] Given I am not a member of the organization, when I request Insights, then the request is
      rejected

## Technical Notes

**Reuse, do not re-implement.** `FindingsBreakdownService::for($organizationId)` — with no
account — already returns exactly this, and has a test covering that path written before this
page existed. `FindingsBreakdown.vue` already takes tabs and emits `select(tabKey, groupKey)`.

**`topServices` stays for now.** `buildKeyInsights()` consumes it to write the "most affected
service" line, and rewriting that is not this story. The new breakdown is added alongside; the
old "Top affected services" *card* is what gets replaced.

**Drill-through is the same plain link** Scan detail uses — `?service=` or `?finding_type=`,
handled by F-7 and F-3.

## Out of Scope

- **The Benchmark tab.** The wireframe shows three tabs; the third is F-6
  ([#89](https://github.com/teemops/tops/issues/89)) and is impossible until F-4
  ([#87](https://github.com/teemops/tops/issues/87)) records a finding's benchmark. Two tabs
  ship here.
- **The security score** — B-2 ([#91](https://github.com/teemops/tops/issues/91)). It is pinned
  at 0 and cannot move, which is a real bug, but replacing it needs a product decision about
  what a score should even be. F-5 does not touch the formula, so the "fix it while you are in
  there" argument does not apply.
- **Changing the period-scoped parts of Insights** — the trend, remediation rate and scan count
  are about a window and stay that way.
- **Removing `topServices`** from the payload while `buildKeyInsights()` still uses it.

## Success Metrics

- The breakdown component is consumed by a second page **without being modified**
- A service's count is the same number on Insights, Scan detail and Findings
- Reaching the filtered list of one service's findings is one click from Insights

## Related Practices

- [Architecture Practices](../practices/architecture.md) — the shared component earning its
  keep; this is the story that proves it
- [Product Practices](../practices/product.md) — replacing a card, not adding a second one
  beside it
- [Security Practices](../practices/security.md) — breakdown counts are tenant data, scoped by
  `organization_id`, with a cross-organization test
- [Testing Practices](../practices/testing.md) — feature tests for the endpoint, including
  isolation and the empty state
