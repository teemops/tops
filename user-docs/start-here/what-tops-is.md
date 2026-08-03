# What TOPS is — and what it isn't

TOPS scans your AWS accounts for security misconfigurations and tells you how to fix them.
It's open source, self-hosted on your own infrastructure, and Apache-2.0 — no paid tier,
no usage limits, no feature gated behind a licence.

## What it does

- **74 checks** across 11 AWS services — S3, IAM, EC2, RDS, CloudTrail, KMS, Lambda,
  DynamoDB, ELBv2, SNS, SQS.
- **Two scan profiles today**: a general "basic" baseline and the CIS AWS Foundations
  Benchmark. You can run either, both, or narrow either one down to specific services.
- **Every finding carries a remediation.** Critical and high-severity findings also carry
  step-by-step guidance with links to AWS documentation — see
  [Reading a finding](../using-tops/reading-a-finding.md).
- **Multi-tenant**: organisations, team roles, and per-organisation data isolation, so one
  install can run scans for more than one team without either seeing the other's accounts.
- **No stored AWS access keys.** TOPS reads your accounts through cross-account IAM roles
  your own administrators create and can revoke — see
  [How TOPS connects to AWS](how-tops-connects-to-aws.md).

## What it isn't

- **Not a runtime protection tool.** TOPS reads configuration through the AWS API; it does
  not watch network traffic, block requests, or sit in front of anything.
- **Not multi-cloud yet.** AWS only. Azure and GCP are on the roadmap, not in the code.
- **Not a compliance guarantee.** A clean scan means the checks TOPS runs found nothing —
  it isn't a substitute for an audit against a framework's full requirement set. The PCI
  profile in particular is not usable yet: it exists but has no rules written, so it's
  hidden from the scan picker rather than shown half-finished.
- **Doesn't fix anything for you.** TOPS tells you what's wrong and how to fix it; applying
  the fix is still a change you make in your own AWS account.
- **No scheduled scans, and no report export**, as of this writing. Both are on the
  roadmap; neither has a controller, a route, or a UI yet — see
  [`docs/PROGRESS.md`](https://github.com/teemops/tops/blob/develop/docs/PROGRESS.md) if
  you want to check what's actually built before relying on something you read about.

## Where to go next

| If you are | Start with |
| --- | --- |
| Deciding whether to allow TOPS into your AWS organisation | [How TOPS connects to AWS](how-tops-connects-to-aws.md) |
| Ready to run it | [Install](install.md) |
| Looking at your first scan results | [Reading a finding](../using-tops/reading-a-finding.md) |

*Source of truth for this page: `README.md` and `docs/PROGRESS.md` in the TOPS repository —
the second is checked against the code, not against intent.*
