# What the IAM role can do

> **Written for security reviewers.** Every permission the TOPS CloudFormation stack grants in
> your AWS account, what uses it, and how to remove the parts you do not want. No prior
> knowledge of TOPS is assumed.

**The role TOPS asks for is not read-only.** On top of two AWS-managed read policies it carries
21 inline policies, 18 of which are unscoped, spanning CloudFormation, CloudTrail, CloudWatch
Logs, AWS Config, Security Hub, GuardDuty, Macie and Inspector.

Scanning uses none of them. Every scan TOPS performs is `list`, `get` or `describe`, and the
write permissions exist for operational features — **none of which are implemented as of
v0.4.0.** The detail is below, along with how to strip them.

## At a glance

| | Grants | Resource-scoped | Used by scanning |
| --- | --- | --- | --- |
| **2 managed policies** | Read across all services | n/a | **Yes — this is all scanning uses** |
| **3 inline policies** | Write, narrowly | Yes | No |
| **18 inline policies** | Write | No — `Resource: "*"` | No |

The stack also creates a **second role** for CloudWatch Events, covered below.

## The read half — everything a scan uses

Two AWS-managed policies, attached to the role:

| Policy | |
| --- | --- |
| `arn:aws:iam::aws:policy/ReadOnlyAccess` | AWS-managed read access across services |
| `arn:aws:iam::aws:policy/ResourceGroupsandTagEditorReadOnlyAccess` | Reads resource groups and tags |

Scan behaviour is defined in JSON, not code — `app/rules/tasks/<service>/tasks.json` — which
means the complete set of API calls TOPS can make is enumerable rather than a matter of trust.
As of v0.4.0 that is **36 operations, of which 35 are `list`, `get` or `describe`, and none
mutate anything.** You can check this yourself; see [Verifying this](#verifying-this).

## The write half — what none of it is for, yet

Grouped by service. Nothing in this section is exercised by a scan.

**Resource-scoped** — these three are constrained to TOPS-named resources:

| Policy | Actions | Limited to |
| --- | --- | --- |
| `TopsPassRole` | `iam:PassRole` | `arn:aws:iam::*:role/tops*` |
| `TopsS3Bucket` | `s3:` CreateBucket, DeleteBucket, ListBucket, GetBucketLocation, ListAllMyBuckets | `arn:aws:s3:::tops-config-*` |
| `TopsCloudFormationAccess` | `cloudformation:` Create/Update/Delete stack, change sets, GetTemplate | `arn:aws:cloudformation:*:*:stack/tops*` |

**Unscoped** — the following carry `Resource: "*"`:

| Service | Actions |
| --- | --- |
| CloudFormation | `ListStacks`, `ListStackResources`, `DescribeStacks` (read; unscoped because these do not support resource-level permissions) |
| CloudTrail | Create, Update, Delete trails · Start/Stop logging · Describe, GetTrailStatus |
| CloudWatch Logs | Create/Delete log groups, streams and metric filters · `PutLogEvents` |
| AWS Config | Put/Delete config rules, configuration recorders, delivery channels, aggregators, conformance packs, remediation configurations and exceptions · Start/Stop the recorder |
| Security Hub | `securityhub:*` |
| GuardDuty | `guardduty:*` |
| Macie | `macie:*` |
| Inspector | `inspector:*` |

The last four are full service control — enable, disable, configure and delete, including the
findings those services hold.

## What we do not claim

- **These write permissions are not used by anything.** TOPS instantiates only STS, SNS and SQS
  clients directly, plus read-only clients built from the scan definitions. There is no code in
  the repository that calls Security Hub, GuardDuty, Macie, Inspector, AWS Config or CloudTrail.
  They are provisioned ahead of features that do not exist yet. **If you would rather not grant
  a permission for a feature nobody has built, remove them** — the next section shows how, and
  scanning is unaffected.
- **`securityhub:*`, `guardduty:*`, `macie:*` and `inspector:*` are broad by any standard.**
  They allow disabling those services and deleting their findings. We are not going to describe
  that as least privilege.
- **`s3:ListAllMyBuckets` in `TopsS3Bucket` can never authorise.** It does not support
  resource-level permissions, and the statement scopes it to `arn:aws:s3:::tops-config-*`. It is
  inert rather than dangerous — but it is there, and you would have found it.
- **We do not claim the role cannot be misused if the parent account is compromised.** Anyone
  who can assume it gets everything above. That is what the `ExternalId` condition and your own
  control of the parent account are protecting.

## The second role

The stack creates `TopsCWEventRole` as well, which is easy to miss:

- **Trusted by** `events.amazonaws.com`, not by TOPS
- **Grants** `events:PutEvents` to the default event bus in the TOPS parent account only
- **Purpose** forwarding EC2 state-change events

It holds no read access to your account and cannot be assumed by TOPS.

## How the role is protected

The trust policy allows exactly one principal — the TOPS parent account you were given — and
only when the caller presents a matching `ExternalId`:

```yaml
Principal:
  AWS: <your TOPS parent account>
Condition:
  StringEquals:
    "sts:ExternalId": <generated for this account alone>
```

The `ExternalId` is generated per account and is what stops a different TOPS install, or anyone
who learns your account id, from assuming the role. It is the
[AWS-recommended defence](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_roles_create_for-user_externalid.html)
against the confused deputy problem.

**You can revoke access at any time** by deleting the CloudFormation stack. TOPS is told, and
the account unlinks itself.

## Removing permissions you do not want

The template is a plain CloudFormation file in an S3 bucket **in your own account**, put there
by your own installer. Nothing stops you editing it before you hand out the link.

1. Download it from the URL in the quick-create link, or take it from
   [`templates/iam.role.child.account.cfn.yaml`](https://github.com/teemops/tops/blob/develop/templates/iam.role.child.account.cfn.yaml).
2. Delete the `AWS::IAM::Policy` resources you do not want. For scanning only, that is every one
   of them — keep the `TeemOps` role, its two managed policies and `TopsCustomNotifier`.
3. Upload it back over the same S3 key, or point `TOPS_CFN_TEMPLATE_URL` at your edited copy.

Scanning keeps working. Anything that later needs the write permissions will fail loudly rather
than silently, because AWS returns an explicit `AccessDenied`.

## Verifying this

Do not take the page's word for it. Against a linked account:

```bash
aws iam list-attached-role-policies --role-name <the TeemOps role>
aws iam list-role-policies --role-name <the TeemOps role>
```

And to confirm the claim that scanning only reads — every API call TOPS can make is declared in
JSON, so grep for a mutating verb and expect nothing:

```bash
grep -rhoE '"(task|start)": "[a-z][a-zA-Z]+"' app/rules/tasks/*/tasks.json | sort -u
```

## Related

- [How TOPS connects to AWS](../start-here/how-tops-connects-to-aws.md) — the wider picture, and what crosses each boundary
- [Reporting a security issue](https://github.com/teemops/tops/blob/develop/SECURITY.md) — including what is already known
- [The template itself](https://github.com/teemops/tops/blob/develop/templates/iam.role.child.account.cfn.yaml)

*Source of truth for this page: `templates/iam.role.child.account.cfn.yaml` and
`app/rules/tasks/*/tasks.json`. Checked against v0.4.0.*
