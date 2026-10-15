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

## Related Practices
- [Product Practices](../practices/product.md) — honest claims; the smallest version that
  solves the problem
- [Security Practices](../practices/security.md) — multi-tenant isolation on findings
- [Code Quality Practices](../practices/code-quality.md) — scan definitions validated
- [Architecture Practices](../practices/architecture.md) — rules as data, F-4 attribution
