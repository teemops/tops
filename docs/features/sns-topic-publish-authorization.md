# Locking down the account-linking SNS topic

> ## 🔴 HIGH PRIORITY — security review, not yet built
>
> The parent account's `teemops-sns` topic accepts `sns:Publish` from **any AWS principal
> on the internet**. This is the one inbound path into a TOPS install, so it is the first
> thing a security reviewer probes. Nothing here is implemented yet; this document records
> the research, the options, and the reasoning so the decision is made once.

Status: **awaiting agreement.** Raised 2026-08-02 while producing the AWS integration
architecture diagram for CISO/CTO audiences — the diagram describes SNS as "the one inbound
path into TOPS", which invites exactly this question.

Tracked as **N-11** in [the roadmap](../roadmap.md#n-11--lock-down-the-account-linking-sns-topic),
split across two issues:
[#100 — consumer-side validation](https://github.com/teemops/tops/issues/100) (phase 1) and
[#101 — install-scoped filter secret](https://github.com/teemops/tops/issues/101) (phase 2).

## The exposure

[`infra/cloud-stack/stackset/sns.topic.cfn.yaml`](../../infra/cloud-stack/stackset/sns.topic.cfn.yaml)
carries this statement:

```yaml
- Sid: allow-all-aws-users
  Effect: Allow
  Principal:
    AWS: "*"
  Action: sns:Publish
  Resource: "*"
  # Condition:
  #   StringEquals:
  #     'sns:MessageAttributes.TopsRawTopsRootAccountHash': 'teemops-123'
```

The commented-out condition was an attempt to fix this that could never have worked. See
[Research findings](#research-findings).

### What is and is not exposed

Be precise about this — it is less bad than it first reads, and overstating it costs
credibility with the reviewers who will read the template themselves.

- **Publish only.** The sibling `alllow-admin-account` statement is scoped by
  `AWS:SourceOwner` to the topic's own account, so nobody can subscribe, delete the topic,
  or change its policy. The exposure is limited to putting messages on the topic.
- **Damage still requires guessing `external_id`**, a v4 UUID from `Str::uuid()`
  ([`AwsAccount.php:42`](../../app/app/Models/AwsAccount.php:42)) — ~122 bits, CSPRNG-backed.
  A forged message that does not match a real record is rejected by
  [`ProcessSqsMessages::handleCreateRequest`](../../app/app/Console/Commands/ProcessSqsMessages.php:262).
- **`unique_id` is not a secret.** It is `organization->org_id`, identical for every account
  in the org and present in API paths. It contributes no entropy. The whole gate is
  `external_id`.
- **`external_id` is a shared-with-third-parties secret.** It travels in the console
  quick-create URL query string into an account admin's browser history, and is stored as a
  CloudFormation stack parameter readable via `cloudformation:DescribeStacks` — a permission
  the TOPS role itself grants (`TopsCloudFormationListAccess`).

### Realistic impact

| Attack | Requires | Impact |
| --- | --- | --- |
| Forged `Delete` | Guessing `external_id` | Silently unlinks a customer's account |
| Forged `Create` | Guessing `external_id` | Repoints a link at an attacker-controlled role |
| Flood the topic | Nothing | Unauthenticated cost + DLQ noise + poller load |

Note what is *not* on this list: data exfiltration. TOPS assuming a role in an attacker's own
account gains the attacker nothing. The credible risks are denial-of-onboarding, data
pollution, and cost. The unglamorous one — anyone can make you pay for SQS messages — is the
one most likely to actually happen.

## Research findings

### You cannot check message shape in an SNS topic policy

Two independent reasons, either one fatal:

1. **`sns:Publish` supports no service-specific condition keys.** SNS defines only
   `sns:Endpoint` and `sns:Protocol`, both of which apply to `Subscribe`. The Service
   Authorization Reference lists no condition keys against the `Publish` action, and none
   exist for message attributes or message body. IAM authorises before the payload matters.
2. **CloudFormation's custom-resource publish carries no message attributes.** All three
   captured samples in [`references/samples/`](../../references/samples/) have no
   `MessageAttributes` key — the entire request lives in the message *body*.

So `sns:MessageAttributes.TopsRawTopsRootAccountHash` was dead on arrival twice over. Do not
revive it.

### Shape checking is available on the subscription

SNS payload-based filtering (`FilterPolicyScope: MessageBody`) evaluates a filter policy
against the JSON body, with nested-property support. It filters *delivery*, not
authorisation — the publish still succeeds and is still billed, but the message never
becomes an SQS message.

A shape-only filter is close to worthless on its own: the child template is served publicly
from `<bucket>/templates/*`, so an attacker can read the exact shape and reproduce it. It
becomes useful only when the filter matches on something an attacker does not know — see
[Proposed design](#proposed-design-install-scoped-filter-secret).

⚠️ **Unverified.** Payload filtering requires a valid JSON body. CloudFormation publishes the
request as a JSON string, which should parse, but this has not been tested against a real
custom-resource message. A filter that silently matches nothing is indistinguishable from a
working integration — test on one account before rollout, and watch
`NumberOfNotificationsFilteredOut-InvalidMessageBody`.

### Who actually publishes

CloudFormation publishes the custom-resource request under the **stack account's principal**,
not a service principal. This is why AWS guidance for cross-account custom resources is to
grant `arn:aws:iam::<account>:root`. It matters because it means principal-based global
condition keys (`aws:PrincipalAccount`, `aws:PrincipalOrgID`) do work here.

### Hard quotas that constrain the options

SNS topic access policy: **30 KB, 100 statements, 1–200 principals.** The 200-principal cap
is binding — any design that whitelists account IDs has a ceiling around 200 linked accounts,
which contradicts the "unlimited child accounts" claim in the architecture diagram and on the
marketing site.

## Options evaluated

| # | Option | Scales | Web tier needs AWS creds | Closes internet-wide access |
| --- | --- | --- | --- | --- |
| 1 | `aws:PrincipalOrgID` condition | Unlimited | No | Yes, within one org |
| 2 | Account-ID allowlist, maintained at onboarding | ~200 accounts | **Yes** | Yes |
| 3 | Install-scoped filter secret (proposed) | Unlimited | No | Mostly — see caveats |
| 4 | Pin the role name so the message carries no authority | Unlimited | No | No — removes the payoff instead |

### Option 1 — `aws:PrincipalOrgID`

```yaml
Condition:
  StringEquals:
    "aws:PrincipalOrgID": !Ref OrganizationId
```

One condition, no per-account work, no ceiling. Now that the product is self-hosted-first,
the overwhelmingly common deployment is "I run TOPS, I scan my own organisation's accounts",
which this fits exactly. The wide-open policy is a leftover from the SaaS model where the
publishing accounts were genuinely unknown strangers.

Cost: cannot link accounts outside the organisation. Make it an optional install-time
parameter rather than mandatory, so the cross-org case stays possible.

### Option 2 — account-ID allowlist

This is what AWS's own APN guidance recommends for this exact vendor-onboarding pattern: add
the customer account ID to the topic policy at onboarding, remove it at stack deletion.
Rejected as a default for three reasons:

- **Caps the product at ~200 accounts** (the principal quota above).
- **Puts AWS credentials back into php-fpm.** The web tier deliberately holds none today —
  [`docker/app/entrypoint.sh:56`](../../docker/app/entrypoint.sh:56) calls this out as a
  design property. `sns:AddPermission` would trade it away.
- Needs a UX change to capture the 12-digit account ID at init;
  [`AwsAccountsController::initCloudFormation`](../../app/app/Http/Controllers/Api/AwsAccountsController.php:102)
  does not collect it today.

### Option 4 — make the message carry no authority

Orthogonal to the others, and worth doing regardless. The only load-bearing content in the
message is `TopsRoleArn`. Pin the role name in the template
(`RoleName: !Sub "tops-audit-${UniqueId}"`) so TOPS *derives* the ARN from the account ID and
never trusts the one in the message. A forged publish then cannot redirect anything.

Cost: `&capabilities=CAPABILITY_NAMED_IAM` on the quick-create URL, and one stack per account
per role name.

## Proposed design: install-scoped filter secret

Proposed by Ben, 2026-08-02. **This is the recommended primary control.**

Generate a GUID once at install time — call it `TopsInstallId` — that is static for the life
of that TOPS installation. Thread it through three places:

1. **Parent SAM stack** takes it as a parameter and sets it in the `TopsSubscriber` filter
   policy.
2. **Child template** takes it as a parameter and passes it into `TopsCustomNotifier` as a
   resource property, so it appears in the message body.
3. **Quick-create URL** gains `&param_InstallId=<guid>`.

```yaml
  TopsSubscriber:
    Type: AWS::SNS::Subscription
    Properties:
      FilterPolicyScope: MessageBody
      FilterPolicy:
        ResourceType: ["Custom::TeemopsPingSNS"]
        ResourceProperties:
          TopsInstallId: !Ref TopsInstallId
```

### Why this is the right shape

It upgrades the filter from a *shape* check (worthless — the template is public) to a *shared
secret* check. It needs no AWS credentials in the web tier, no topic policy mutation, no
per-account state, and has no 200-principal ceiling. It is strictly better than Option 2 on
every axis except one: it is a weaker authentication boundary.

### What it is honestly worth

**It is a speed bump, not an authentication boundary.** Document it that way, internally and
externally, or it will be oversold to a reviewer who then finds the gap themselves.

- **The secret is shared with every account admin you onboard.** It is in a URL query string,
  in browser history, and in a CloudFormation stack parameter in *their* account.
- **CloudFormation parameters are readable.** `cloudformation:DescribeStacks` returns
  parameter values in plaintext — and the TOPS role itself grants that permission in every
  linked account. Set `NoEcho: true` on the parameter to mask it; this is a real improvement
  but still a speed bump, not secrecy.
- **It never rotates,** by design. One leak is permanent and compromises the whole install,
  not one account — unlike `external_id`, which is per-account.

Net effect: raises the bar from *anyone on the internet* to *anyone who has ever been given
an onboarding link, or who can read a stack in any linked account*. That is a large and
worthwhile reduction. It is not "the topic is now private".

### Open issues to resolve before building

1. **Silent failure is the main risk.** A mismatched GUID — typo, stale bookmarked link,
   reinstall, restore-from-backup — means SNS drops the message with no trace. The child
   stack then hangs until CloudFormation's custom-resource timeout (~1 hour) and rolls back
   with an unactionable error, and TOPS has nothing to log because the message never arrived.
   This is the same failure class CLAUDE.md warns about for scan rules.

   **Mitigation:** add a quarantine subscription with an inverse filter so filtered-out
   traffic is visible rather than silent, and alarm on it:

   ```yaml
   FilterPolicy:
     ResourceProperties:
       TopsInstallId: [{"anything-but": ["<this install's guid>"]}]
   ```

   This surfaces both misconfiguration and attack traffic on the same queue.

2. **Rotation must be designed in now.** Make the filter accept a *list* —
   `TopsInstallId: ["<current>", "<previous>"]` — so the value can be rotated without
   breaking in-flight onboarding. Trivial to include now, ugly to retrofit.

3. **Persistence and restore.** The GUID must be written to `generated/teemops.env` alongside
   the other stack outputs, backed up, and **never regenerated if already present**. If a
   re-run of `install.sh` mints a new one, every previously issued onboarding link breaks and
   — worse — every existing linked account's `Delete` ping is silently filtered, so unlinking
   stops working with no error. The installer needs an explicit reuse path.

4. **Both child templates need the parameter**, not just one:
   `iam.role.child.account.cfn.yaml` and `iam.role.audit.account.cfn.yaml`.

5. **Verify payload filtering works against a real CloudFormation message** before this is
   relied on (see the warning under [Research findings](#research-findings)).

## Recommended sequencing

1. **Consumer-side hardening.** No AWS changes, no design decisions, ship first — see
   acceptance criteria below.
2. **Install-scoped filter secret** (proposed design) with the quarantine subscription and
   list-based rotation.
3. **`aws:PrincipalOrgID`** as an opt-in install parameter, defaulting on when an org ID is
   supplied. Composes with #2 and closes the residual gap for the common case.
4. **Pinned role name** (Option 4), so the message stops being authoritative at all.

Skip the account allowlist unless cross-org linking becomes a requirement — the
200-principal ceiling and reintroducing AWS credentials to php-fpm cost more than they return.

## User acceptance criteria

**Consumer-side hardening (phase 1)**

- [ ] Given a `Create` message whose `TopsRoleArn` account differs from the account in
      `StackId`, when it is processed, then it is rejected and logged — both values are
      already in the message and neither is checked today
- [ ] Given a `Create` message matching an account whose status is not `pending`, when it is
      processed, then it is rejected rather than repointing an existing link
- [ ] Given a pending record older than the configured link window, when a matching `Create`
      arrives, then it is rejected as expired
- [ ] Given any message failing to match a record, when it is processed, then a counter is
      incremented that an operator can alarm on — not only a `Log::warning`
- [ ] Given an SNS envelope with an invalid signature, when the poller reads it, then it is
      rejected, for parity with `SnsSignatureVerifier` on the HTTP path

**Install-scoped filter secret (phase 2)**

- [ ] Given a fresh install, when the AWS step runs, then a `TopsInstallId` GUID is generated,
      persisted to `generated/teemops.env`, and used in the subscription filter policy
- [ ] Given an install that already has a `TopsInstallId`, when `install.sh --aws-only` is
      re-run, then the existing value is reused and no onboarding link is invalidated
- [ ] Given a quick-create URL, when an admin opens it, then the install id is present as a
      `NoEcho` parameter and reaches TOPS in the message body
- [ ] Given a message carrying a wrong or absent install id, when it is published, then it is
      delivered to the quarantine queue and does not reach `teemops_main`
- [ ] Given a message carrying a *correct* install id, when it is published, then linking
      completes exactly as it does today — proven end to end against a real AWS account, not
      a synthetic message

## Adjacent findings

Noticed while researching; not part of this work, but they touch the same files.

- **`iam.role.audit.account.cfn.yaml` hardcodes a parent account** — [#102](https://github.com/teemops/tops/issues/102).
  `ParentAWSAccountId` defaults to `660228977852` (Teem's SaaS account) and the notifier's
  `ServiceToken` is built from `AWS::Region` rather than a parent-region parameter — unlike
  the child template, which was fixed to take `ParentDeploymentRegion`. As shipped, a
  self-hosted user deploying the audit template with defaults points their stack at someone
  else's account. Worth its own issue.
- **`TopsMainSQSPolicy` grants `SQS:ReceiveMessage` to `Principal: "*"`.** Harmless in
  practice — the `aws:SourceArn` condition can never match a direct caller, so it is dead
  permission — but it reads alarmingly in a review. Drop `ReceiveMessage` from the statement.

## Sources

- [Actions, resources, and condition keys for Amazon SNS](https://docs.amazonaws.cn/en_us/IAM/latest/UserGuide/list_amazonsns.html) — no condition keys for `Publish`
- [Amazon SNS API permissions reference](https://docs.amazonaws.cn/en_us/sns/latest/dg/sns-access-policy-language-api-permissions-reference.html) — policy quotas: 30 KB, 100 statements, 200 principals
- [Introducing payload-based message filtering for Amazon SNS](https://aws.amazon.com/blogs/compute/introducing-payload-based-message-filtering-for-amazon-sns/)
- [Amazon SNS subscription filter policy scope](https://docs.aws.amazon.com/sns/latest/dg/sns-message-filtering-scope.html)
- [Collecting information from CloudFormation resources created in external accounts](https://aws.amazon.com/blogs/apn/collecting-information-from-aws-cloudformation-resources-created-in-external-accounts-with-custom-resources) — AWS's own guidance for this pattern
- [SNS topic access policy for cross-account custom resources (re:Post)](https://repost.aws/questions/QUZA3fW7kgTFix7g1p58MEvA/what-sns-topic-access-policy-to-use-for-a-cloudformation-customresource-to-post-messages-to-a-sns-topic-in-different-aws-account)
- [Allow other AWS accounts to publish to an SNS topic](https://repost.aws/knowledge-center/sns-topic-organization-accounts-publish)
- [Amazon SNS-backed custom resources](https://docs.aws.amazon.com/AWSCloudFormation/latest/UserGuide/template-custom-resources-sns.html)
