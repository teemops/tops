# Security policy

TOPS is a security tool that reads customers' AWS accounts. A vulnerability here can expose
another organisation's cloud posture, so we would rather hear about a problem early and
awkwardly than late and politely.

## Reporting a vulnerability

**Do not open a public issue.** Use one of these instead:

1. **[Report a vulnerability privately on GitHub](https://github.com/teemops/tops/security/advisories/new)**
   — preferred. It keeps the report private, and lets us develop and review the fix in a
   private fork before anything is disclosed.
2. **security@teemops.com** — if you would rather not use GitHub, or the form is unavailable.

You do not need to have a fix, a CVE, or a polished write-up. A rough description of
something that looks wrong is worth sending.

### What to include

Whatever you have. The more of this you can give us, the faster we can confirm it:

- What the issue is, and what an attacker gets out of it
- The steps to reproduce, ideally against a local install
- The version — `git rev-parse HEAD`, or the image tag from `docker compose images app`
- Whether you have told anyone else, and whether you intend to publish

## What happens next

| | |
| --- | --- |
| **Acknowledgement** | Within 5 working days |
| **Initial assessment** | Within 10 working days — whether we can reproduce it, and our severity view |
| **Fix and release** | Depends on severity. We will tell you the plan and keep you updated |
| **Credit** | Named in the advisory and the changelog, unless you would rather not be |

TOPS is maintained by a small team. If you have not heard from us inside those windows,
please chase — the most likely explanation is that a message went astray, not that we are
ignoring it.

## What we are most worried about

If you are looking for somewhere to start, these are the failures that would hurt most, in
order:

1. **Cross-organisation data access.** Every query touching tenant data is supposed to be
   scoped by `organization_id`. Anything that lets one organisation read another's accounts,
   scans or findings is the highest-severity class of bug in this codebase.
2. **Authentication or authorisation bypass** on any route.
3. **Anything that lets a third party influence account linking** — the CloudFormation
   custom resource, the SNS to SQS path, or the handling of `external_id` — such that an
   account is linked, unlinked, or repointed without the owner's action.
4. **Privilege escalation through the CloudFormation templates**, or a template that grants
   more in a customer account than the documentation says it does.
5. **Secrets handling** — anything that writes credentials to logs, images, or the repository.

## Scope

**In scope**

- The application in `app/`
- The CloudFormation templates in `templates/` and `infra/`
- The installer (`install.sh`), the Docker images, and `docker-compose.yml`
- The published `teem/tops` and `teem/tops-base` images

**Out of scope**

- **Anything that requires shell access to the host TOPS runs on.** This is a self-hosted
  application; an operator with a shell can read the database by design. See
  [D-9](docs/roadmap.md) on why application-level encryption at rest was deliberately removed.
- **Misconfiguration of your own install** — running with `APP_DEBUG=true` on a public
  address, exposing MySQL's port, or reusing credentials.
- **Findings the scanner reports about your AWS account.** Those are the product working. If
  you think a rule is wrong, open a normal issue.
- Missing security headers, cookie flags, or scanner output with no demonstrated impact.
- Denial of service against teemops.com, social engineering, and physical attacks.
- Vulnerabilities in AWS itself — report those to AWS.

**Never test against infrastructure you do not own.** Reproduce against a local install or
your own AWS account. A report is not worth another organisation's data.

## Already known — please do not report these

These are public, tracked, and being worked on. Reporting them again is not a finding.

| | |
| --- | --- |
| [#101](https://github.com/teemops/tops/issues/101) | The parent account's `teemops-sns` topic accepts `sns:Publish` from any AWS principal. The subscription now screens messages on a per-install id and quarantines the rest, so a forged publish does not reach the queue — but the topic itself is still openly publishable, and the issue stays open until that is closed off. Background: [`docs/features/sns-topic-publish-authorization.md`](docs/features/sns-topic-publish-authorization.md) |
| [#109](https://github.com/teemops/tops/issues/109) | The `teemops_main` queue policy grants `SQS:ReceiveMessage` to `Principal: "*"`. The `aws:SourceArn` condition means it cannot actually authorise a direct caller, so it is dead permission rather than exposure — but it is known, and being removed |

## Supported versions

Only the latest release. TOPS is pre-1.0 and moving quickly; fixes land on `develop` and go
out in the next release rather than being backported.

| Version | Supported |
| --- | --- |
| 0.4.x | Yes |
| < 0.4 | No — upgrade |

Upgrading is a tag change and a restart. See
[Upgrading and rolling back](https://github.com/teemops/tops#upgrading-and-rolling-back).

## Disclosure

We aim to publish an advisory once a fix is released, describing the issue, the affected
versions and the fix. We will agree timing with you, and we would ask that you hold off
publishing until a fixed release exists — or 90 days from your report, whichever comes first.

If a vulnerability is already being exploited, tell us and we will move immediately rather
than to any schedule.

## What we will not do

We will not pursue legal action against anyone who reports a vulnerability in good faith,
follows this policy, and does not access or destroy data that is not theirs. There is no
bug bounty — this is an open-source project without a budget for one, and we would rather
say so than imply otherwise.
