# TOPS documentation

TOPS scans your AWS accounts against CIS and TOPS' own baseline, and turns what it finds
into findings you can act on. This site covers evaluating, connecting, and using it.

Install and upgrade commands live in the [`README.md`](https://github.com/teemops/tops)
at the repository root — this site links to them rather than restating them.

**This site is growing, not finished.** More sections arrive as the features behind them
ship. What exists so far:

## Start here

- [What TOPS is — and what it isn't](start-here/what-tops-is.md)
- [How TOPS connects to AWS](start-here/how-tops-connects-to-aws.md) — for whoever has to
  approve TOPS before it's installed.
- [Install](start-here/install.md)
- [Connect your first AWS account](start-here/connect-your-first-aws-account.md)
- [Run your first scan](start-here/run-your-first-scan.md)

## Using TOPS

- [Reading a finding](using-tops/reading-a-finding.md) — what each field means, and where
  to start on a long list.
- [Resolving a finding](using-tops/resolving-a-finding.md) — fixing, ignoring, and what the
  next scan does.

## AWS accounts

- [What the IAM role can do](aws-accounts/what-the-iam-role-can-do.md) — the honest,
  policy-by-policy answer to "what does this actually grant?"

## Security

- [The security model](security/the-security-model.md)
- [Reporting a vulnerability](security/reporting-a-vulnerability.md)
