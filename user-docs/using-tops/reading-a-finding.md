# Reading a finding

What each part of a finding means, and how to get from a list of them to the handful that
matter today.

A **finding** is one rule failing against one resource in one AWS account. It is a lasting
record, not a line in a scan report: the same misconfiguration on the same bucket is the same
finding next week, with the same history, whether you have scanned once or fifty times.

## Before you start

- At least one AWS account connected, and one completed scan

## The anatomy of a finding

| | |
| --- | --- |
| **Severity** | `critical`, `high`, `medium` or `low`. Set by the rule, not by your environment |
| **Title** | What failed, in a few words — *IAM User MFA Token* |
| **Service** | The AWS service the rule covers — `iam`, `s3`, `rds`, `ec2`, and seven others |
| **Resource** | The specific thing that failed: the resource id and its type. This is what makes it *your* finding rather than a generic warning |
| **Description** | What the rule checks, and why it matters |
| **Remediation** | What to do about it. Every rule has one |
| **Recommendation** | Sometimes: a grouped piece of guidance covering several related rules, with steps and links to AWS documentation |
| **Status** | `open`, `resolved` or `ignored` — see [Resolving a finding](resolving-a-finding.md) |
| **NZISM controls** | On findings from an NZISM scan: each NZISM control the check relates to, as its control id (CID), paragraph and level — **MUST** before **SHOULD** — for example *CID 6843 · 16.4.37.C.02 · MUST* |

## What the severities mean

Severity comes from the rule and does not change based on your environment. TOPS ships 74
rules:

| | Rules | What it means |
| --- | --- | --- |
| **Critical** | 4 | Exploitable now, with wide blast radius. All four concern the root account — access keys existing, or MFA missing |
| **High** | 24 | A real weakness that an attacker could use as a step, or a control that is off when it should be on |
| **Medium** | 32 | Hygiene and hardening. Worth fixing, rarely worth paging anyone |
| **Low** | 14 | Best practice and tidiness |

Severity is not the same as urgency. A `medium` on a resource that faces the internet may
matter more than a `high` on something isolated — the finding tells you which resource, so
you can make that call.

## Narrowing the list

The findings list opens on everything currently open, which for a first scan of a real
account is usually a lot. Four filters cut it down, and they combine:

1. **Service** — pills across the top, each with a count. Clicking the active one clears it.
2. **Benchmark** — `basic`, `cis` or `nzism`. One finding can belong to more than one —
   a check in both Basic and NZISM, from a scan that ran both, is one finding listed under
   each. **All** counts each finding once; findings that belong to no benchmark are not
   counted under any.
3. **AWS account** — when you have more than one connected.
4. **Status** — `open` by default. Resolved findings are hidden unless you ask for them.

**Filters live in the address bar.** Whatever you have narrowed to can be bookmarked or
pasted to a colleague, and they will land on the same view. This is the fastest way to hand
someone their share of the work.

## A reasonable first pass

1. Filter to **critical**, and deal with those first. There are only four rules that can
   produce one, and all four are about the root account.
2. Then **high**, filtered to one service at a time. Working service by service beats working
   down the list, because the fix is often the same for every resource in a group.
3. Leave **medium** and **low** until the first two are empty. They will still be there.

## When it does not say what you expect

**A finding I fixed is still listed.**
Findings update when a scan looks again. Run a new scan for that account; if the rule now
passes, TOPS resolves the finding itself. See [Resolving a finding](resolving-a-finding.md).

**The benchmark counts do not add up to the total.**
Expected. A finding can belong to more than one benchmark, and findings from before
benchmarks were recorded belong to none.

**An NZISM finding lost its NZISM controls.**
A finding belongs to the benchmarks of the scan that last looked at it. Scan that account
with the NZISM profile again and they return.

**Is my account NZISM compliant?**
TOPS cannot say, and does not. The NZISM profile checks the part of the NZISM that a
configuration scan can evidence — most of the manual covers governance, people and process
no scanner can see. Use the findings as evidence for the controls they name, not as an
assessment.

**A service I use has no findings at all.**
Either nothing failed, or TOPS does not cover that service yet — it currently has rules for
IAM, S3, RDS, EC2, CloudTrail, KMS, Lambda, SQS, SNS, DynamoDB and ELBv2. An uncovered
service looks exactly like a clean one, which is worth knowing.

**Two findings look identical.**
Check the resource id. One rule failing against two resources is two findings, and they are
resolved independently.

## Next

- [Resolving a finding](resolving-a-finding.md) — fixing, ignoring, and what the next scan does
