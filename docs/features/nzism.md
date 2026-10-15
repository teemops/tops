# NZISM ruleset — coverage and plan

*Researched 2026-10-15 for **D-16** in [`roadmap.md`](../roadmap.md). Not yet a user story:
G-1 goes through `feature-development` when it starts. The owner has validated the need in
design discussions with government contacts, so Discovery is short.*

## What NZISM is, for this purpose

The **New Zealand Information Security Manual** (GCSB/NCSC), currently **v3.9, November
2025** — confirmed by the manual's own section export (see below), and matching AWS's
"2025-11 Version 3.9"; the May 2025 NCSC release note was the first 3.9 issue. Controls carry
an ID (CID), a paragraph number and a compliance level:

- **MUST / MUST NOT** — baseline. Not complying needs a dispensation backed by a documented
  agency risk assessment.
- **SHOULD / SHOULD NOT** — may be risk-managed; the agency's Accreditation Authority accepts
  the residual risk.

It is mandated across government through the Protective Security Requirements. Cloud content
sits mainly in **Ch 22.1** (cloud computing) and **Ch 23** (public cloud security: governance,
identity, data protection, logging); certification and accreditation (C&A) is Ch 4–5.

### From the manual itself

The NZISM site exports each section as CSV with one row per paragraph: `Chapter, Section,
Sub-Section, Block, Paragraph, Classifications, Compliance, CID, ControlText`. Control rows
(`…C.nn.`) carry the CID, `Must`/`Should` and the classification; rationale rows (`…R.nn.`)
do not. **The CID is the same number AWS's mapping uses** (`nzism_control_id`), so the two
join directly — that is where G-1a's `controls` field should come from.

*Only section 1.1 has been exported so far (2026-10-15, owner-supplied). AWS's mapping draws
on 22 sections: 7.1, 12.4, 14.1, 14.5, 16.1, 16.3, 16.4, 16.6, 16.7, 17.1, 17.4, 17.5, 17.9,
18.1, 18.4, 19.1, 20.3, 20.4, 22.1, 23.3, 23.4, 23.5. With those, every CID in the matrix
can be checked against the manual's own text and level rather than AWS's. Reproduction terms
for the manual's text are not yet checked, so the exports are not committed to the repo.*

**Section 1.1 is the spec for G-3.** It is NZISM's own rule for non-compliance:

| CID | Paragraph | Level | What it requires |
| --- | --- | --- | --- |
| 127 | 1.1.64.C.01 | MUST | A dispensation from a baseline control is granted by the **Accreditation Authority** |
| 131 | 1.1.65.C.01 | MUST | It rests on an agency risk assessment documenting the **reason** it cannot comply, the **effect** on the agency's and any shared systems, the **alternative mitigations**, their **strength and applicability**, the **residual risk**, and a **review date** |
| 143 | 1.1.67.C.01 | MUST | Systems shared across agencies or All-of-Government: interested parties formally **consulted** first |
| 146 | 1.1.68.C.01 | SHOULD | Non-compliance decisions **reviewed at least annually** |
| 151 | 1.1.69.C.01 | MUST | The risk assessment and decision are **retained** |
| 152 | 1.1.69.C.02 | MUST | Not implementing recommended (SHOULD) controls is **recorded**, and the residual risk **agreed by the Accreditation Authority** |

*The rest of this document still rests on NCSC pages and AWS's documentation for anything
outside section 1.1. Verify against the manual before quoting it to an agency.*

## AWS's mapping

AWS publishes NZISM conformance packs for AWS Config
([Foundation](https://docs.aws.amazon.com/config/latest/developerguide/operational-best-practices-for-nzism-foundation.html),
[Extension](https://docs.aws.amazon.com/config/latest/developerguide/operational-best-practices-for-nzism-extension.html)),
mapping NZISM 3.9 controls for RESTRICTED and below to Config rules. The source YAML is
CC0 ([awslabs/aws-config-rules](https://github.com/awslabs/aws-config-rules)).

| Pack | Controls | Distinct checks | Notes |
| --- | --- | --- | --- |
| Foundation | 45 (18 MUST, 26 SHOULD, 1 untagged) | 91 | Sydney and global regions — **AWS says it does not currently deploy to the NZ region** |
| NZ Transition | — | 61 | The subset of Foundation available in Auckland: no IAM, GuardDuty, SSM-patch or OpenSearch checks |
| Extension | 7 (4 MUST, 3 SHOULD) | 12 | Opt-in per check: backup plans, Multi-AZ, WAF, KMS for S3/SNS |
| **Union** | **46** | **103** | |

AWS calls these *sample* mappings. They cover only the technical controls a configuration
check can see — most of NZISM (governance, documentation, physical, personnel) is out of
reach of any scanner, TOPS included.

## Where TOPS stands

Every (control, check) pair, with TOPS's status, is in
**[`nzism/aws-config-mapping.csv`](nzism/aws-config-mapping.csv)**. By distinct check:

| Status | Checks | Meaning |
| --- | ---: | --- |
| **covered** | 25 | An existing TOPS rule checks the same thing |
| **partial** | 9 | An existing rule checks part of it (e.g. ALB/NLB but not classic ELB) |
| **data** | 5 | A new rule on data TOPS already collects — JSON only |
| **collect** | 11 | A new API call in a service TOPS already scans, then a rule |
| **new** | 53 | A service TOPS does not scan yet |

**By control: 30 of the 46 have at least one TOPS check behind them today**, 24 of those
through a fully covered check. The 53 "new" checks are concentrated: GuardDuty 6,
OpenSearch 6, Redshift 5, CloudFront 4, SSM 4, AWS Backup 4, API Gateway 3, SageMaker 3,
then a long tail of one or two each.

**The Auckland point is worth leading with.** AWS's own Foundation pack does not deploy to
ap-southeast-6, and the in-region Transition pack drops IAM, GuardDuty and patching. TOPS
calls the APIs directly and already scans every region the account has enabled (it lists them
with `describeRegions`), so it can evaluate Auckland workloads the managed packs cannot.
*Verify on a real Auckland workload before claiming it* — G-4.

## What has to change in TOPS

From the rule-engine inventory:

1. **One rule, several frameworks.** F-4 already records a finding against a *list* of
   rulesets, but `scan:validate-rules` rejects a rule id that appears in more than one
   ruleset file — so CIS today restates basic checks under new ids and one problem raises two
   findings. Relaxing that check lets `nzism.json` **reuse** the 25 covered rules by id, with
   no duplicate findings and no engine change.
2. **Control references.** A rule maps to a benchmark only by naming convention (`cis-1.4`);
   nothing parses it. NZISM needs a real field — `controls: [{framework, cid, paragraph,
   level}]` — carried to the finding and shown on it, because "which NZISM control, MUST or
   SHOULD" is the whole value to an assessor.
3. **A report by control**, not by finding: each in-scope NZISM control, MUST/SHOULD, and
   pass / fail / not assessed, with the accepted-risk findings and their reasons. This is the
   C&A evidence. It is the old *Report export* Later item, triggered by this work.
4. **Accepted risk with a reason.** TOPS can ignore a finding (and it stays ignored, D-11).
   NZISM 1.1.65–1.1.69 says exactly what turns that into evidence: reason, effect,
   alternative mitigations and their strength, residual risk, who accepted it (the
   Accreditation Authority), and a review date no more than a year out — after which the
   acceptance lapses and the finding is open again.
5. **Profile registration** — a `nzism` entry in `ScanProfilesService::$profiles` and in the
   `profiles` list of each service's `tasks.json`.

## Plan

In order, each shippable on its own:

- **G-1a — NZISM profile from what TOPS has.** Relax the cross-ruleset uniqueness check,
  `nzism.json` reusing the 25 covered rules, the 5 data-only rules, and a `controls` field
  shown on findings. JSON plus a small validator and UI change.
- **G-1b — fill the existing services.** The 11 *collect* checks: EBS default encryption,
  volumes and snapshots; RDS snapshots; S3 account-level block-public-access and bucket
  policy; IAM credential report and hardware root MFA; CloudTrail event selectors; DynamoDB
  KMS. Then upgrade the 9 *partial* checks where the new data allows.
- **G-1c — new services, by control weight.** GuardDuty and Security Hub first (they back the
  most MUST controls), then CloudWatch Logs (retention, encryption), AWS Backup, ACM, and the
  rest **only as an agency's workload needs them** — an OpenSearch rule nobody runs is
  coverage for a slide, not for a customer.
- **G-2 — NZISM report** (point 3 above) and **G-3 — accepted risk with a reason** (point 4).

## How to talk about it

Say **"maps to N of the NZISM controls AWS identifies as configuration-checkable"**, with the
matrix to back it. Never "NZISM compliant": no scanner can make that claim, an assessor
would know it, and it is the kind of overstatement the teemops.com README warns against.
