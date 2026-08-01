# Filter Findings by Compliance Benchmark - User Story

**Roadmap:** F-4 · **Issue:** [#87](https://github.com/teemops/tops/issues/87) ·
**Milestone:** Findings & Scans v2 · **Status:** ✅ **Shipped 2026-08-02** · **Raised:** 2026-08-02

> **Note for anyone upgrading.** Existing findings carry no benchmark, by design, so the
> Benchmark pills do not appear until a scan raises findings with `rulesets` set. On the
> reference install that meant 144 findings showing no benchmark until the next scan. This is
> the "blank rather than wrong" criterion working, not a fault — but it does mean the feature
> is invisible on first upgrade.
>
> **One thing this exposed:** the migration was written and tested but not applied to the dev
> database, so Findings returned "Failed to load findings" until `php artisan migrate` ran.
> Container deploys are unaffected — `docker/app/entrypoint.sh` migrates on start — but a
> local non-container environment needs it run by hand.

**Depends on:** D-1 durable findings ([#81](https://github.com/teemops/tops/issues/81)), landed —
the benchmark attaches to the durable finding, so it is written once rather than per scan.

> **The only genuine schema change in this workstream.** Everything else reused columns that
> already existed. This one adds a column because the information is genuinely not recorded
> anywhere: `scan_results` has no ruleset, and a scan can run several rulesets at once, so it
> cannot be inferred from the scan either.

## User Story

As a solo engineer who has to answer to a compliance requirement, I want to filter findings to
one benchmark, so that I can see my CIS gaps without wading through everything else the
scanner noticed.

## Expected Behavior

Findings gains a **Benchmark** row of pills beside the existing Service ones — Basic, CIS, and
whatever is authored later — each showing how many findings it would give. Selecting one
narrows the list; selecting it again clears it. It behaves like the service filter because it
*is* the service filter's pattern, and the two combine.

A benchmark with no rules, such as PCI, is not offered at all — the same rule the New Scan
modal already follows.

**Findings raised before this ships have no benchmark and show none.** They are not guessed at.
A finding wrongly labelled CIS is worse than one honestly labelled nothing, particularly for
the user who came here because of an audit.

## Why not derive it from the rule name

`cis-5.1` starts with `cis`, so the benchmark looks derivable. It is not, for two reasons.

The rule format lets one rule belong to several rulesets. **No rule does today** — Basic has 52,
CIS has 22, and they do not overlap — but the moment one does, a prefix says one thing and the
truth is two. Deriving would be silently wrong exactly when the answer matters.

And `tops-s3-001` carries no prefix at all. Half the derivation would be a lookup anyway, so
the "cheap" option is not cheap, only fragile.

## Recording it, and the multi-ruleset case

The column is a **list**, not a single value, because "which benchmark raised this" genuinely
can have more than one answer. A finding that is both a Basic and a CIS gap says so.

The alternative — putting the ruleset into a finding's identity — is wrong and worth recording
as rejected: it would create two findings for one problem on one resource, which is precisely
the duplication D-1 was built to remove.

`evaluateScan` currently merges every ruleset's rules into one flat list and loses which
ruleset each came from. It will instead key rules by id while collecting the rulesets each
belongs to, so a rule appearing in two rulesets is **evaluated once** carrying both. That
removes a latent double-evaluation at the same time.

The rulesets are written as part of a finding's **content**, not just on creation, so the most
recent scan that examined a resource stays authoritative — the same rule D-1 set for severity,
title and remediation. If a rule moves between rulesets in a later release, the next scan
corrects it.

## User Acceptance Criteria

- [x] Given a finding is raised, when it is written, then the ruleset that raised it is
      recorded on it
- [x] Given a rule belonging to two rulesets, when it raises a finding, then both are recorded
      and the finding is created once, not twice
- [x] Given the Findings page, when it loads, then I see a benchmark pill per benchmark that
      has findings, each with a count
- [x] Given I select a benchmark, when the list reloads, then only that benchmark's findings
      are shown and the URL reflects it
- [x] Given I select a benchmark and a service, when the list reloads, then both filters apply
- [x] Given a benchmark whose ruleset has no rules, such as PCI, when the pills render, then it
      is not offered
- [x] Given findings created before this shipped, when I view them, then they show no benchmark
      rather than a guessed one, and are not counted under any benchmark filter
- [x] Given another organization's findings, when benchmark counts are computed, then none of
      them are counted

## Technical Notes

**Schema:** a nullable `rulesets` JSON column on `scan_results`, mirroring `scans.rulesets`.
Reversible, and with **no data migration** — existing rows stay null deliberately, which is the
"blank rather than wrong" criterion rather than an omission.

**Filtering** uses `whereJsonContains`. Verified available on the SQLite the tests run against
(JSON1, `json_each`, SQLite 3.45.1) as well as MySQL, before choosing this shape.

**Benchmark facet counts loop; service facets do not, and that is deliberate.** F-3 insisted on
one grouped query because there are eleven-plus services and that number grows. There are
**two** benchmarks with rules, and a `GROUP BY` over a JSON array needs `json_each`, whose
syntax is not portable between MySQL and SQLite. Two `whereJsonContains` counts are simpler,
portable, and cheaper than the query that would avoid them. If benchmarks ever reach double
figures this should be revisited — noted so the inconsistency is a decision, not a drift.

**The facet follows the same rule the service facet does:** it is computed without the
benchmark filter applied, so selecting one does not zero the others.

## Out of Scope

- **Backfilling existing findings.** They show blank until their next scan re-raises them, at
  which point they are correct. A backfill would have to guess.
- **Insights by benchmark** — F-6 ([#89](https://github.com/teemops/tops/issues/89)), which this
  unblocks but does not include.
- **A benchmark tab on the breakdown component** — also F-6.
- **Multi-select benchmarks.** Single-select, matching the service filter.
- **Changing what a benchmark means**, or authoring PCI.

## Success Metrics

- "Show me only my CIS gaps" is answerable in one click
- No finding is ever labelled with a benchmark that did not raise it
- F-6 needs no further data-model work

## Related Practices

- [Database Practices](../practices/database.md) — reversible migration, data migration
  deliberately absent, no premature index
- [Security Practices](../practices/security.md) — benchmark counts are tenant data, scoped by
  `organization_id`, with a cross-organization test
- [Product Practices](../practices/product.md) — reuses the pill pattern rather than inventing
  a second filter idiom
- [Testing Practices](../practices/testing.md) — engine tests for what gets recorded, feature
  tests for filtering and isolation
