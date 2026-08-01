# Filter Findings by Service - User Story

**Roadmap:** F-3 · **Issue:** [#85](https://github.com/teemops/tops/issues/85) ·
**Includes:** F-7 ([#83](https://github.com/teemops/tops/issues/83), bug, prerequisite) ·
**Milestone:** Findings & Scans v2 · **Status:** ✅ **Shipped 2026-08-02** · **Raised:** 2026-08-01

> **Verified 2026-08-02** by Ben on the reference install — service pills filter correctly and
> every acceptance criterion below is met. Backed by 6 feature tests including
> cross-organization isolation. [#85](https://github.com/teemops/tops/issues/85) closed, and
> F-7 ([#83](https://github.com/teemops/tops/issues/83)) with it.

> **Why F-7 is in here.** F-3's second acceptance criterion is "…and the URL reflects it".
> `Findings/Index.vue` initialises its filters to `''` and calls `onMounted(() => loadData())` —
> it reads no query parameters at all. F-3 cannot be demonstrated without F-7, and F-7 is XS.
> F-7 is a bugfix and needs no story of its own; it is recorded here so the two are reviewed
> and tested as one change.

## User Story

As a solo engineer looking at a list of AWS security findings, I want to narrow it to one
service, so that I can work through "the EC2 problems" as a single job instead of scrolling
past everything else.

## Expected Behavior

The Findings page gains a row of service pills above the list — one per service that actually
has findings, most findings first, each showing its count. Clicking a pill narrows the list to
that service; clicking the active pill clears it. Services with no findings are not offered.

The counts are **facets**: each pill shows how many findings you would get *if you clicked it*,
computed against whatever other filters are already active. Selecting EC2 therefore does not
flatten every other pill to zero — the counts stay meaningful, so the pills remain a way to
navigate rather than a dead end.

The long tail collapses behind a `+ N more` expander, because the service list grows with every
ruleset added and a wall of pills is its own kind of unusable.

Separately, and underneath all of it: **the page reads its filters from the URL and writes them
back.** `/findings?service=ec2` lands filtered, changing a filter updates the address bar, and
the resulting link can be bookmarked or sent to a colleague. This is what S-1's drill-through
will land on.

## User Acceptance Criteria

**Service filter (F-3)**

- [x] Given the Findings page, when it loads, then I see one service pill per service with
      findings, ordered most findings first, each showing its count
- [x] Given I click a service pill, when the list reloads, then only that service's findings
      are shown and the URL reflects it
- [x] Given a service with no findings, when the pills render, then it is not shown
- [x] Given I click the active pill, when the list reloads, then the filter clears and all
      services are shown again
- [x] Given another filter is active, such as an AWS account, when the pill counts render,
      then they respect that filter
- [x] Given a service filter is active, when the pill counts render, then the other pills
      still show their own counts rather than zero — the service filter does not constrain
      its own facet
- [x] Given a pill shows a count of N, when I click it, then the list contains N findings —
      the pill and the list agree
- [x] Given more services have findings than fit comfortably, when the pills render, then the
      tail collapses behind a `+ N more` control that reveals the rest

**Reading and writing the URL (F-7)**

- [x] Given `/findings?service=ec2`, when the page loads, then only EC2 findings are shown and
      the EC2 pill is the active one
- [x] Given `/findings?finding_type=cis-3.9`, when the page loads, then both the list and the
      type control are filtered
- [x] Given I change any filter in the UI, when the list updates, then the URL updates to match
- [x] Given an unknown or malformed parameter value, when the page loads, then it is ignored
      and the page renders unfiltered rather than erroring

**Authorization and isolation**

- [x] Given findings belonging to another organization, when I request service facet counts,
      then none of that organization's findings are counted
- [x] Given I am not a member of the organization, when I request findings or facets, then the
      request is rejected

## Technical Notes

**Most of the server side exists.** `?service=` already filters (`FindingsController::index`),
`scan_results.service` is indexed, and `useFindings` already forwards the parameter. What is
missing is the **facet counts** and the **control**.

**Facet counts are one grouped query, not N counts.** A single
`selectRaw('service, COUNT(*)')->groupBy('service')` over the same joined, organization-scoped
query the list uses. Do not loop.

**The one rule that makes faceting behave:** the service facet is computed with every *other*
active filter applied but **not** the service filter itself. Applying it would collapse the
other pills to zero the moment one is selected, which is exactly the trap the existing
finding-type dropdown works around by accumulating a union of every type it has seen
(`Index.vue:20-25`). A server-computed facet replaces that workaround for service; the type
dropdown is left alone in this change.

**Pills must match the list.** `index` currently applies the resolved-exclusion
(`status != 'resolved'`) to the *summary* counts but not to the *list* query. Facets follow the
**list** semantics, so a pill reading 12 yields 12 rows. Anything else is a bug the user can
see. The existing summary/list inconsistency is out of scope here — noted, not fixed.

**Counts come from the API, never from the loaded page.** The list is paginated (limit 100), so
deriving pills from `findings` would silently undercount on any account with more.

**Correctness depends on D-1**, which shipped ([#81](https://github.com/teemops/tops/issues/81)) —
before durable findings, counts double-counted across scans.

## Out of Scope

- **Multi-select.** Single-select for v1, matching the dropdown it sits beside. Multi-select
  doubles the query and the state handling; add it when someone asks.
- **Benchmark filter** — that is F-4 ([#87](https://github.com/teemops/tops/issues/87)) and needs
  a data-model change, since nothing currently records a finding's benchmark.
- **Severity pills**, or faceting any filter other than service.
- **Replacing the finding-type dropdown** with pills, or faceting its counts.
- **A `?scan_id=` filter or "from scan …" chip** — explicitly dropped by the wireframes when
  D-1 made findings current state. Recorded so it is not re-proposed.
- **Persisting filter choices** across sessions or organization switches.

## Success Metrics

- Selecting a service takes one click from the Findings page, and clearing it takes one more
- A pill's count and the resulting row count agree — no reconciliation needed by the user
- A copied URL reproduces the same filtered view for a colleague
- S-1 ([#86](https://github.com/teemops/tops/issues/86)) can link straight to `?service=ec2`
  and land filtered, with no further work in this area

## Related Practices

- [Product Practices](../practices/product.md) — simplicity first; single-select and one
  grouped query rather than a general faceting framework nobody asked for
- [Security Practices](../practices/security.md) — facet counts are tenant data and must be
  scoped by `organization_id`; a cross-organization test ships with the change
- [Database Practices](../practices/database.md) — one grouped query against the indexed
  `scan_results.service`, not a count per service
- [Testing Practices](../practices/testing.md) — feature tests for the facet endpoint
  including cross-organization rejection
