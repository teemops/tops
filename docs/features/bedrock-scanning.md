# Scanning Amazon Bedrock — Research

**Status:** Research only. No user story, no issue, no commitment. · **Written:** 2026-08-11
· **Roadmap:** Later, under *Expand scanner coverage*

This document answers two questions: **what does it actually take to secure an Amazon
Bedrock environment**, and **what would TOPS have to do to check it**. It stops short of a
user story deliberately — see [Recommendation](#recommendation) for why this is a *Later*
item and what should trigger promoting it.

---

## 1. The control surface, and the half of it a scanner cannot see

The single most important thing to get right before writing any Bedrock rules is that
"Bedrock security" names two different problems, and a configuration scanner can only
address one of them.

| | **Configuration plane** | **Inference plane** |
| --- | --- | --- |
| Examples | Invocation logging off; no guardrail; custom model unencrypted; agent role over-scoped; no VPC endpoint | Prompt injection; jailbreak; data exfiltration through model output; PII leakage in completions; agent action abuse |
| Visible to | `Get*`/`List*` API calls — a CSPM | Only the request/response bodies at runtime |
| TOPS can check it | **Yes** | **No** |

Every credible source frames the *interesting* Bedrock risk as the second column —
[Palo Alto Unit 42's "Agent God Mode"](https://unit42.paloaltonetworks.com/exploit-of-aws-agentcore-iam-god-mode/)
and [Sonrai's AgentCore privilege-escalation write-up](https://sonraisecurity.com/blog/aws-agentcore-privilege-escalation-bedrock-scp-fix/)
both describe chains that end in reading a knowledge base or a code interpreter session.
But the *enabling conditions* for those chains are almost all in the first column: an
over-broad role, a missing guardrail, logging that would have caught it turned off.

**So the honest framing for TOPS is:** we check that the controls which make the runtime
attacks detectable and containable are actually turned on. We do not detect prompt
injection, and the product should never imply otherwise. That framing also keeps the
feature inside what the scan engine can express, which matters a lot for cost — see §4.

There is a second reason this distinction earns its place: **Bedrock ships insecure by
default in the ways that matter here.** Model invocation logging is off, guardrails are
opt-in and not enforced on invocation, and customer-managed encryption is not applied
unless asked for. An account that has "just started using Bedrock" fails most of these
checks without anyone having made a bad decision — which is exactly the kind of finding a
solo engineer can act on in an afternoon.

---

## 2. The controls worth checking

Ordered roughly by value-per-unit-of-work. "API" is the call that reveals it; all are
`Get`/`List` and all are covered by the existing role (§4.2).

### Tier 1 — the ones that would ship first

**1. Model invocation logging is enabled.** `bedrock:GetModelInvocationLoggingConfiguration`

Off by default, per region. It captures the prompt and completion bodies for
`InvokeModel`, `Converse` and their streaming variants. Without it there is no forensic
record of what was asked or answered — credential misuse, jailbreak attempts and
exfiltration through completions all leave no trace, and unexplained spend cannot be
attributed. This is the Bedrock equivalent of CloudTrail being off, and it is the one
check every vendor implements first. Note CloudTrail records the *fact* of an
`InvokeModel` call but not the bodies; the two are not substitutes.

*Severity: high. Response shape: `loggingConfig` absent or empty when disabled.*

**2. At least one guardrail exists.** `bedrock:ListGuardrails`

Guardrails are the only mechanism Bedrock offers for content filtering, denied topics,
PII redaction and prompt-attack detection. An account invoking models with no guardrail
configured at all has no runtime control of any kind. Weak as a check — it says nothing
about whether the guardrail is *used* — but it is cheap and the zero case is unambiguous.

*Severity: medium.*

**3. Guardrails have a prompt-attack filter.** `bedrock:GetGuardrail`

Within `contentPolicy.filters`, a filter of `type: PROMPT_ATTACK` is what defends against
jailbreak and injection attempts. A guardrail configured only for profanity is common and
gives false comfort. Check the filter exists and its `inputStrength` is not `NONE`.

*Severity: high.*

**4. Guardrails have a sensitive-information filter.** `bedrock:GetGuardrail`

`sensitiveInformationPolicy.piiEntities` / `.regexes`. This is what stops PII travelling
out in a completion. Empty on a guardrail attached to anything customer-facing is a real
finding.

*Severity: medium.*

**5. Agents reference a guardrail.** `bedrock-agent:ListAgents`

An agent can invoke tools and read knowledge bases, so an unguarded agent is the highest-
consequence version of check 2. Conveniently, `AgentSummary` already carries
`guardrailConfiguration`, so this needs the list call only — no per-agent `GetAgent`. Worth
also flagging a `guardrailConfiguration` that names a guardrail which no longer exists,
which is a silent failure mode.

*Severity: high.*

### Tier 2 — encryption, all the same shape

Each is "a customer-managed KMS key is not configured, so the AWS-managed default is in
use". That is a *weaker* finding than it sounds — the data is encrypted either way — so
these should be **low or medium, never high**, and the remediation should be honest that
this is a key-custody and revocation improvement, not the difference between encrypted and
plaintext. Getting this wrong is how a scanner trains people to ignore it.

| Control | API | Field |
| --- | --- | --- |
| Custom model encrypted with CMK | `bedrock:GetCustomModel` | `modelKmsKeyArn` |
| Agent encrypted with CMK | `bedrock-agent:GetAgent` | `agent.customerEncryptionKeyArn` |
| Guardrail encrypted with CMK | `bedrock:GetGuardrail` | `kmsKeyArn` |
| Prompt encrypted with CMK | `bedrock-agent:GetPrompt` | `customerEncryptionKeyArn` |

The custom-model one is the most defensible of the four: a fine-tuned model embeds the
training data, so it is closer to being sensitive material than the others.

### Tier 3 — real, but not expressible cheaply

**Log destination hardening.** The S3 bucket receiving invocation logs should be
encrypted, versioned, and ideally protected with Object Lock or MFA Delete. TOPS already
has five S3 rules; the gap is *linking* the bucket named in `loggingConfig.s3Config` to the
S3 findings. The rules engine evaluates one stored response at a time (`$data`), so a
cross-service condition is not expressible today. **Deliberately out of scope** — the
generic S3 rules already fire on that bucket, they just do not say "and this is where your
prompts are stored".

**IAM over-permission on Bedrock roles.** Prowler checks for `AmazonBedrockFullAccess`
attachments and for agent roles with wildcard resources; the Unit 42 and Sonrai research
is entirely about auto-generated roles granting account-wide S3 and memory access. This is
the highest-value item in the whole document and **the hardest for TOPS to do**, because it
means evaluating policy documents, not reading a boolean. It is also not really a Bedrock
feature — it is IAM analysis that happens to be pointed at Bedrock. Should be considered
separately from Bedrock service coverage, and probably after it.

**VPC endpoint / PrivateLink usage.** Prowler has `bedrock_vpc_endpoints_configured`. It
is checkable via EC2 `describeVpcEndpoints`, but "no PrivateLink endpoint" is only a
finding if you have decided Bedrock traffic must not traverse the internet — a policy
choice, not a misconfiguration. High false-positive rate for the target user. Skip.

**AgentCore.** The `bedrock-agentcore` and `bedrock-agentcore-control` clients exist in the
SDK and this is where the most alarming published research sits. It is also the newest and
least stable surface. Out of scope for a first pass; revisit once the base service is
proven.

---

## 3. What the comparators do

[Prowler](https://github.com/prowler-cloud/prowler) — named in D-7 as the closest
comparator — ships **13 Bedrock checks**:

```
bedrock_agent_guardrail_enabled                      bedrock_guardrails_configured
bedrock_agent_role_least_privilege                   bedrock_model_invocation_logging_enabled
bedrock_api_key_no_administrative_privileges         bedrock_model_invocation_logs_encryption_enabled
bedrock_api_key_no_long_term_credentials             bedrock_prompt_encrypted_with_cmk
bedrock_full_access_policy_attached                  bedrock_prompt_management_exists
bedrock_guardrail_prompt_attack_filter_enabled       bedrock_vpc_endpoints_configured
bedrock_guardrail_sensitive_information_filter_enabled
```

Its collection layer calls exactly five things —
`get_model_invocation_logging_configuration`, `list_guardrails` + `get_guardrail`,
`list_agents` + `get_agent`, `list_prompts` + `get_prompt`, and tags. **That is a very
small surface**, and it maps almost one-to-one onto §2 Tier 1 and Tier 2. The convergence
is reassuring: the useful checks here are few and well-agreed, so this is not an open-ended
research problem.

**There is no CIS benchmark for Bedrock.** The CIS AWS Foundations Benchmark does not cover
it, and no equivalent AI-specific benchmark has consolidated. Practically this means
Bedrock rules go in `basic.json` and **not** `cis.json` — there is no authority to cite, so
inventing CIS control numbers would be dishonest. It also means TOPS would be defining its
own opinion, which is fine, but the rule descriptions have to carry their own
justification rather than pointing at a standard.

---

## 4. How it fits TOPS

This is the good news, and it is better than expected.

### 4.1 Zero PHP

`GenericAwsScanner` builds its client from a manifest key and validates operations against
the bundled AWS API model, so any service the SDK knows about works without code. Verified
against the installed SDK (**3.369.9**), which ships all eight Bedrock clients:

```
bedrock          getModelInvocationLoggingConfiguration     OK
bedrock          listGuardrails / getGuardrail              OK
bedrock          listCustomModels / getCustomModel          OK
bedrock-agent    listAgents / getAgent                      OK
bedrock-agent    listKnowledgeBases / getKnowledgeBase      OK
bedrock-agent    listPrompts / getPrompt                    OK
```

Every operation §2 needs resolves through `GenericAwsScanner::supportsOperation()` today.
No custom scanner is required — neither of the two reasons S3 and IAM needed one applies.

### 4.2 No customer redeployment

`templates/iam.role.child.account.cfn.yaml` attaches `ReadOnlyAccess`, and that policy
grants **every `bedrock:` read action §2 needs** — confirmed individually for
`GetModelInvocationLoggingConfiguration`, `ListGuardrails`, `GetGuardrail`, `ListAgents`,
`GetAgent`, `ListKnowledgeBases`, `GetKnowledgeBase`, `ListPrompts`, `GetPrompt`,
`ListCustomModels` and `GetCustomModel`. So `rules/README.md`'s claim holds: **Bedrock
scanning would work against every account already onboarded with no CloudFormation change.**

*(The policy grants roughly fifty `bedrock:` actions in total. No exact figure is quoted
here because two reads of the AWS reference page disagreed on the count — the per-action
confirmations above are what the design rests on, and those were checked one by one.)*

Two gaps, neither in scope: `ListImportedModels`/`GetImportedModel` and
`ListMarketplaceModelEndpoints` are *not* in `ReadOnlyAccess`. Avoid depending on them.

### 4.3 Two directories, not one

`config.client` in `tasks.json` is a single manifest key, and Bedrock's control plane is
split across two clients. So this is **two service directories**, not one:

- `rules/tasks/bedrock/` — `client: "bedrock"` — logging config, guardrails, custom models
- `rules/tasks/bedrockagent/` — `client: "bedrock-agent"` — agents, knowledge bases, prompts

This needs no engine change, but it does mean the UI shows two services in the scan-service
tree and the Findings service facet, which is a small honesty cost — a user thinks of it as
"Bedrock". Worth a `label` of `Bedrock` and `Bedrock Agents` so the pairing reads clearly.
The alternative — teaching `config` to accept multiple clients — is a real engine change for
a cosmetic gain and should not be done for this.

### 4.4 Response shapes are lowerCamelCase

Unlike most AWS services, Bedrock's API models use lowerCamelCase members. Rule conditions
would read `$data['loggingConfig']`, `$data['agent']['guardrailConfiguration']`,
`$data['knowledgeBase']['storageConfiguration']` — not the PascalCase every existing rule
uses. This is a footgun precisely because a wrong key produces **no finding and no error**,
which is the silent-failure mode `scan:validate-rules` exists to catch and which it cannot
catch here. Shapes confirmed against the bundled model:

```
GetModelInvocationLoggingConfiguration → loggingConfig{cloudWatchConfig, s3Config,
                                          textDataDeliveryEnabled, ...}
GetGuardrail  → {guardrailId, name, status, topicPolicy, contentPolicy{filters[]{type,
                 inputStrength, inputEnabled}}, sensitiveInformationPolicy{piiEntities,
                 regexes}, kmsKeyArn}
ListAgents    → agentSummaries[]{agentId, agentName, agentStatus, guardrailConfiguration}
GetAgent      → agent{agentId, agentResourceRoleArn, customerEncryptionKeyArn,
                 guardrailConfiguration, ...}
GetCustomModel→ {modelArn, modelName, modelKmsKeyArn, trainingDataConfig, ...}
```

---

## 5. The one real obstacle

Everything above is easy. This is not, and it should be settled **before** any Bedrock rule
is written, because it is a data-model problem rather than a rules problem.

### Account-level findings on a regional service collide

`getModelInvocationLoggingConfiguration` is the highest-value check in §2 and it has an
awkward shape: it is **account-level** (it describes no resource) but **per-region**.

TOPS has the account-level pattern already — IAM's `getAccountPasswordPolicy` is a task
with no `id`, no `key` and empty `items`, and `tops-iam-013` fires on it. But IAM is
`regional: false`, so it runs exactly once. Bedrock is regional, so the same task runs once
per enabled region — **about 17** on a typical account, which is not a guess: the roadmap
records ~153 region jobs on a full scan, and 9 of the 11 services are regional.

Now look at how a finding is identified. `ScanResult::identityHash()`
([ScanResult.php:98](../../app/app/Models/ScanResult.php#L98)) hashes:

```
organization_id · aws_account_id · service · resource_type · resource_id · finding_type
```

**Region is not in it.** And `FindingsEngine::createFinding()`
([FindingsEngine.php:244](../../app/app/Services/RulesEngine/FindingsEngine.php#L244))
falls back to `'unknown'` for both `resource_type` and `resource_id` when the detail carries
none — which is exactly the account-level case.

So all ~17 regions produce the **same identity hash**. Under D-11's durable findings that is
one row with a unique index on `identity_hash`, and **the last region job to finish wins**.
Given PERF-6 runs five region workers concurrently, which region that is varies per scan.
The user-visible consequence: an account with Bedrock logging enabled in `us-east-1` and
nowhere else shows a finding that appears, disappears and reappears between scans with no
underlying change.

This is not hypothetical and it is not a Bedrock quirk — **it is a latent property of the
identity key that Bedrock is simply the first service to hit**, because it is the first
regional service wanting an account-level check. Any future one has the same problem.

**Three ways out**, in increasing order of cost:

1. **Set a synthetic per-region resource id.** Have the account-level task record the region
   as the resource id (`resource_type: "region"`, `resource_id: "us-east-1"`). Findings
   become per-region, distinct, and read correctly — "logging disabled in eu-west-1". Needs
   a small change to how scan details are recorded for id-less tasks, not a schema change.
   **This is almost certainly the right answer.**
2. **Add region to the identity hash.** Correct in principle, but it is a schema-affecting
   change to a settled model, requires a backfill or an accepted discontinuity across every
   existing finding, and D-11 was closed deliberately. Not worth it for one service.
3. **Make Bedrock `regional: false`.** Scans one region, reports the other sixteen clean.
   This is exactly the silent-failure mode `rules/README.md` warns about and PERF-2 deleted
   a code path for. **Rejected.**

### The related false-positive, which option 1 does not fix

Prowler [#5674](https://github.com/prowler-cloud/prowler/issues/5674) reports the same
underlying issue from the user's side: Bedrock model access is requested per region, and
checking logging in a region where nobody has ever used Bedrock produces a failure the user
cannot act on. With option 1 above, a user who uses Bedrock in one region gets **16 findings
telling them to enable logging in regions they do not use**.

For a product whose stated differentiator is *actionability over coverage*, sixteen
unactionable findings is worse than no Bedrock coverage at all. The fix is to make the rule
conditional on evidence Bedrock is in use in that region — the presence of any guardrail,
agent, custom model or provisioned throughput. That is a cross-task condition, which the
current one-response-at-a-time evaluator cannot express. **This is the part that would need
design work, and it is why this is not a small item.**

The cheap version worth considering first: ship only the checks that are *resource-scoped*
(guardrail filters, agent guardrails, CMK encryption). Those fire only when a resource
exists, so they cannot produce a finding in an unused region, and they need neither option 1
nor a cross-task evaluator. **Model invocation logging — the single most valuable check —
is the one that carries all the difficulty.** That trade is the crux of any sizing.

---

## 6. Rough sizing

| Slice | What | Size |
| --- | --- | :---: |
| A | Two `tasks.json` files; resource-scoped rules only (guardrail filters, agent guardrail, CMK ×4). No engine change. | **S** |
| B | Per-region resource id for account-level tasks, plus the invocation-logging rule. | **S–M** |
| C | Region-usage gating so logging findings only fire where Bedrock is used. Needs a cross-task condition. | **M** |
| D | Bedrock IAM role analysis (§2 Tier 3). Separate feature, arguably not Bedrock at all. | **L** |

A alone is a defensible first ship and is genuinely small. A+B without C is the tempting
middle and is **the one combination to avoid** — it is precisely how you ship sixteen
unactionable findings.

---

## Recommendation

**Later, not Now, and not Next.** Applying the roadmap's own test — *does this help someone
run the scanner and act on what it finds?* — the answer today is "only if they use Bedrock",
and nobody has said they do. The milestone is explicitly recruitment-limited, and the
roadmap already names the risk of engineering absorbing capacity while nobody is watching.
This is a new service in a product that has eleven and whose active workstream is about
making the existing findings readable.

It also fails the fourth decision-framework question — *what are we willing to remove to add
this?* — because nothing is proposed for removal.

**What should trigger promoting it:**

- A design partner says they run Bedrock. That is the whole trigger; one is enough, because
  §4 shows the marginal cost is low and §2 shows the useful check list is short.
- Or: TOPS decides to position against AI-workload security specifically, which is a
  positioning decision and not a roadmap one.

**What is worth doing regardless, and sooner:** the identity-hash collision in §5 is a real
latent defect in a shipped model, not a Bedrock issue. It should be recorded as a known
limitation of D-11 whether or not Bedrock is ever scanned, so that the next regional
account-level check does not rediscover it in production.

---

## Sources

- [Amazon Bedrock security best practices — AWS docs](https://docs.aws.amazon.com/bedrock/latest/userguide/security-best-practices.html)
- [Implementing least privilege access for Amazon Bedrock — AWS Security Blog](https://aws.amazon.com/blogs/security/implementing-least-privilege-access-for-amazon-bedrock/)
- [Configure model invocation logging — AWS Prescriptive Guidance](https://docs.aws.amazon.com/prescriptive-guidance/latest/patterns/configure-bedrock-invocation-logging-cloudformation.html)
- [`GetModelInvocationLoggingConfiguration` API reference](https://docs.aws.amazon.com/botocore/latest/reference/services/bedrock/client/get_model_invocation_logging_configuration.html)
- [ReadOnlyAccess AWS managed policy reference](https://docs.aws.amazon.com/aws-managed-policy/latest/reference/ReadOnlyAccess.html)
- [Prowler — AWS Bedrock checks](https://github.com/prowler-cloud/prowler/tree/master/prowler/providers/aws/services/bedrock)
- [Prowler #5674 — Bedrock checks should validate model usage per region](https://github.com/prowler-cloud/prowler/issues/5674)
- [`bedrock_model_invocation_logging_enabled` — Prowler Hub](https://hub.prowler.com/check/bedrock_model_invocation_logging_enabled)
- [Cracks in the Bedrock: Agent God Mode — Palo Alto Unit 42](https://unit42.paloaltonetworks.com/exploit-of-aws-agentcore-iam-god-mode/)
- [AWS AgentCore: The Overlooked Privilege Escalation Path — Sonrai](https://sonraisecurity.com/blog/aws-agentcore-privilege-escalation-bedrock-scp-fix/)
- [Amazon Bedrock security and governance — IAM condition keys, SCP design, PrivateLink](https://hidekazu-konishi.com/entry/amazon_bedrock_security_and_governance_guide.html)
- [Detect Amazon Bedrock misconfigurations with Datadog Cloud Security — AWS ML Blog](https://aws.amazon.com/blogs/machine-learning/detect-amazon-bedrock-misconfigurations-with-datadog-cloud-security)
