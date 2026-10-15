# NZISM Scan Profile (G-1a) - User Story

*Roadmap item G-1a; direction in D-16. Coverage analysis behind it:
[`nzism.md`](nzism.md) and [`nzism/aws-config-mapping.csv`](nzism/aws-config-mapping.csv).
Discovery agreed 2026-10-15: the users are an agency's security or cloud engineer and its
IT security manager or assessor, equally; this story serves both at the finding level, and
G-2's report serves the assessor at the control level.*

## User Story
As a security engineer or security manager at a New Zealand government agency, I want to
scan an AWS account against the NZISM and see which NZISM control each finding relates to
and whether that control is a MUST or a SHOULD, so that I can act on gaps in the terms my
agency is assessed against instead of mapping TOPS's findings to the manual by hand.

## Expected Behavior
The scan modal offers an **NZISM** profile alongside Basic and CIS. Choosing it runs the
TOPS checks that evidence NZISM 3.9 (November 2025) controls. Every finding from those checks
shows, next to its remediation, the NZISM control or controls it relates to — the control
identifier (CID), the paragraph number and the compliance level — with MUST controls listed
before SHOULD. The Findings page can be filtered to NZISM like any other benchmark.

The profile is described honestly: it covers the subset of NZISM that a configuration scan
can check, built from the checks TOPS already has. Nothing in the product says an account is
"NZISM compliant".

A problem found by both a Basic and an NZISM scan is one finding, attributed to both.

## User Acceptance Criteria

**Happy path**
- [ ] Given an organization member starting a scan, when they open the profile choice, then
      **NZISM** is offered with a description saying it checks the configuration-checkable
      subset of NZISM 3.9 controls
- [ ] Given an NZISM scan of an account with a failing check, when the scan completes, then
      each resulting finding shows every NZISM control it relates to as CID, paragraph and
      level (e.g. "CID 6843 · 16.4.37.C.02 · MUST"), MUST before SHOULD
- [ ] Given the Findings page, when it is filtered to the NZISM benchmark, then only findings
      from checks in the NZISM profile are listed, and the filter's count matches the list
- [ ] Given a check that belongs to both Basic and NZISM, when the same resource fails it in a
      Basic scan and an NZISM scan, then there is one finding, attributed to both benchmarks,
      not two
- [ ] Given a finding from the NZISM profile, when it is shown, then its remediation is the
      same as the existing check's — NZISM adds attribution, not new advice
- [ ] Given RDS clusters without deletion protection or Multi-AZ, RDS instances without log
      exports, and S3 buckets encrypted with S3-managed rather than KMS keys, when an NZISM
      scan runs, then each produces a finding with its NZISM controls (the four new checks)

**Correctness of the mapping**
- [ ] Given any control shown on a finding, then its CID, paragraph and level are those of the
      NZISM 3.9 (November 2025) manual, not AWS's mapping — e.g. CID 6843 and CID 6953 show
      **MUST**, CID 7437 shows **SHOULD**
- [ ] Given a CID that is not in the November 2025 manual, then it is not shown on any finding
- [ ] Given a malformed control reference in a ruleset (unknown framework, missing CID or
      paragraph, a level other than MUST / MUST NOT / SHOULD / SHOULD NOT), when
      `php artisan scan:validate-rules` runs, then it fails and names the rule

**Errors and authorization**
- [ ] Given a member of one organization, when they request NZISM findings, then they see only
      their own organization's findings — never another's
- [ ] Given a scan request naming a profile that does not exist, then it is rejected with a
      validation error, as today

**Wording**
- [ ] Given any screen that shows the NZISM profile or its controls, then no text claims an
      account or organization is "NZISM compliant"

## Technical Notes
- **Checks in the profile (up to 27 existing + 4 new):** the 27 existing rules that fully
  cover an AWS NZISM check — including `tops-iam-012` against CID 1945 (see Decisions) — less any
  overlaps Design removes, plus four new rules on
  data TOPS already collects — RDS cluster deletion protection, RDS cluster Multi-AZ, RDS
  log exports, S3 default encryption with KMS. `ec2-instances-in-vpc` is excluded: it tests
  for EC2-Classic, which AWS retired, so it can never fire. The exact list is settled in
  Design.
- **Reuse without duplicate findings.** F-4 already records a finding against a list of
  rulesets, but `scan:validate-rules` forbids a rule id in more than one ruleset. Lifting
  that restriction (for rules that are identical) is what lets NZISM reuse a check without
  restating it under a new id.
- **Control references** need a real field on a rule; today a benchmark is only implied by
  the rule id (`cis-1.4`) and nothing reads it. The manual's control *text* is not shown or
  stored: the export states no reproduction terms (open question).
- Overlapping checks inside the profile (e.g. `tops-iam-013` and `cis-1.8`/`cis-1.9` on the
  password policy) are resolved in Design so one problem does not raise two NZISM findings.
- Run `php artisan scan:validate-rules` after every rules change.

## Success Metrics
- **Demo-ready:** an NZISM scan of a real account shows NZISM-attributed findings in the first
  government conversation.
- **One step:** from any NZISM finding, the engineer sees its control and level without
  leaving the finding.
- **Zero wrong levels:** every CID/paragraph/level shown matches the November 2025 manual,
  checked by a test against the mapping.
- **No duplicate findings** for a problem caught by both Basic and NZISM.

## Out of Scope
- New AWS API calls or new services — G-1b and G-1c.
- The 9 *partial* checks — upgraded in G-1b where new data allows.
- Data-location inventory and offboarding — G-1d.
- A report by control, coverage percentages or any score — G-2 (D-12: no scores).
- Risk acceptance with reasons — G-3.
- Showing the manual's control text — pending reproduction terms.
- Verifying scans in the Auckland region — G-4.

## Decisions
- **`tops-iam-012` (root access keys) is included against CID 1945** (16.3.5.C.01, MUST — use of
  privileged accounts controlled and accountable). AWS mapped it only to CID 1946, which is not
  in the November 2025 manual; 1945 is its closest successor. Agreed 2026-10-15 to confirm with
  the first customer rather than hold the check back.

## Design

*Proposed 2026-10-15.*

### `nzism.json` is a mapping, not a second copy of the checks

```json
{
  "$schema": "tops/findings/ruleset/schema.v1",
  "name": "nzism.json",
  "framework": "NZISM",
  "version": "3.9 (November 2025)",
  "controls": {
    "6843": { "paragraph": "16.4.37.C.02", "level": "MUST", "section": "16.4 Privileged Access Management" }
  },
  "rules": [
    { "ref": "tops-iam-001", "controls": ["6843", "6953", "7436", "1841", "7437"] }
  ]
}
```

- **`rules` holds references**, not rules: `{ "ref": "<existing rule id>", "controls": [CIDs] }`.
  The condition, severity and remediation stay in `basic.json` or `cis.json`, so NZISM can
  never drift from the check it reuses — "NZISM adds attribution, not new advice".
- **`controls` is a catalogue keyed by CID** — each control's paragraph, level and section
  title, once. Generated from the manual's export (the script is recorded in
  [`nzism.md`](nzism.md)); carries **section titles only, never control text**.
- **The four new checks go in `basic.json`** (`tops-rds-010` cluster deletion protection,
  `tops-rds-011` cluster Multi-AZ, `tops-rds-012` log exports, `tops-s3-006` KMS default
  encryption) and `nzism.json` references them. They are ordinary hygiene, useful to every
  user, so a Basic scan gains them too.

### One loader resolves references

The ruleset JSON is parsed in four places today (`FindingsEngine`, `ScanProfilesService`,
`ComplianceScoreService`, `ValidateScanRules`). A small `RulesetLoader` reads a ruleset and
resolves each `ref` to the referenced rule from the other ruleset files; the three runtime
readers go through it. `FindingsEngine::evaluateScan` is otherwise unchanged: a referenced
rule keeps its id, so F-4's existing merge attributes the finding to every ruleset the scan
ran that contains it. **No schema change, no migration.**

### Validation

`scan:validate-rules` learns three things: a `ref` must name a rule that exists in another
ruleset and must not carry its own condition; every CID a rule lists must exist in the
catalogue; and every catalogue entry has a paragraph shaped `N.N.N.C.NN` and a level of
MUST / MUST NOT / SHOULD / SHOULD NOT. The cross-ruleset id-uniqueness check stays for
full rules — references are how a rule legitimately appears twice.

### What the user sees

- **Scan modal** — an **NZISM** profile, offered automatically once `nzism.json` has rules
  and the eight services it uses list `nzism` in their `tasks.json` profiles. Description:
  *"Checks mapped to NZISM 3.9 controls — the subset a configuration scan can evidence."*
- **Findings list** — under the title of any finding attributed to NZISM, one line:
  `NZISM  CID 6843 · 16.4.37.C.02 · MUST  ·  CID 7437 · 23.3.19.C.02 · SHOULD`, MUST first,
  no click needed. The controls reach the page the way `tips.json` guidance already does — a
  `controlsMap` keyed by rule id alongside the findings — so nothing is stored per finding and
  a mapping fix shows on existing findings immediately.
- **Benchmark filter** — NZISM appears as a pill through F-4's existing facets.

### Decisions in this design

1. **No NZISM score on Insights.** `ComplianceScoreService` would otherwise show "NZISM 72%"
   automatically — a percentage of *checks passing*, which reads as a compliance level and is
   exactly what the story rules out. NZISM is excluded from those scores; G-2 decides how
   control-level status is presented, with "not assessed" made explicit.
2. **Overlaps resolved:** `cis-1.8` is left out of NZISM — its condition is identical to
   `tops-iam-013` (minimum length 14), so including both would raise two findings for one
   problem. `cis-1.9` (password reuse) stays: it checks something different.
3. **Attribution follows F-4:** a finding is attributed to the rulesets of the scan that last
   evaluated it. A scan run with Basic *and* NZISM gives one finding attributed to both; a
   later Basic-only scan re-attributes it to Basic. *This refines the story's "attributed to
   both" criterion: it holds for a scan that runs both profiles.*

### The decision framework

1. *Simpler for the end user?* Yes — the control is on the finding they are already reading.
2. *Simpler to maintain?* Yes — one loader instead of four parsers; NZISM holds no conditions.
3. *Less complexity for the same outcome?* Restating rules under `nzism-*` ids needs no
   loader, but duplicates every finding and every condition. The reference costs one class.
4. *What do we remove?* The four duplicated JSON parsers.

### Not handled in v1
- Showing NZISM controls on findings from scans that did not run the NZISM profile.
- Control-level status, coverage or any percentage (G-2).
- The finding detail page (`Findings/Show.vue`) — the list line covers the demo; added if
  the first customer reads findings there.

## Related Practices
- [Product Practices](../practices/product.md) — honest claims; the smallest version that
  solves the problem
- [Security Practices](../practices/security.md) — multi-tenant isolation on findings
- [Code Quality Practices](../practices/code-quality.md) — scan definitions validated
- [Architecture Practices](../practices/architecture.md) — rules as data, F-4 attribution
