# AWS Scanner Coverage & Research

Research and a staged coverage plan for AWS security scanning: which services matter,
which misconfigurations matter, and the order to work through them.

**Scope note.** This document used to be the whole project plan — architecture, API
specs, deployment, an initial requirements interview. Those sections were pruned on
2026-07-29 because they described a hosted multi-tenant SaaS on EC2, which is not the
product being built. What survives is the scanner research, which is still good and is
what the roadmap points at when it says "expand scanner coverage".

**Where things live now:**

| For | See |
| --- | --- |
| What's planned and in what order | [`roadmap.md`](./roadmap.md) — the plan of record |
| What's actually built | [`PROGRESS.md`](./PROGRESS.md) |
| System architecture | [`architecture.md`](./architecture.md) |
| Feature specs and user stories | [`features/features-spec.md`](./features/features-spec.md) |
| API reference | [`laravel-app/API_DOCUMENTATION.md`](./laravel-app/API_DOCUMENTATION.md) |
| How to add a service or rule | `app/rules/README.md` — read this first |
| Self-hosted deployment | [`../docker-compose.README.md`](../docker-compose.README.md) |

---

## Research Basis

Compiled from:

- Top 20 AWS services requiring security auditing
- Top 100 AWS security misconfigurations and vulnerabilities
- CIS AWS Foundations Benchmark v5.0.0 (40 controls)
- AWS Security Hub CSPM controls (500+)
- Prowler security checks (584+ across 85 AWS services)
- Industry practice, 2025–2026

---

## Current Coverage

**11 services** have a `tasks.json`. **74 rules** authored across two rulesets.

| Service | `basic.json` | `cis.json` | Total |
| --- | ---: | ---: | ---: |
| IAM | 13 | 7 | 20 |
| RDS | 9 | 3 | 12 |
| EC2 / VPC | 7 | 5 | 12 |
| CloudTrail | 5 | 4 | 9 |
| S3 | 5 | 2 | 7 |
| KMS | 3 | 1 | 4 |
| Lambda | 4 | — | 4 |
| DynamoDB | 2 | — | 2 |
| ELBv2 | 2 | — | 2 |
| SNS | 1 | — | 1 |
| SQS | 1 | — | 1 |
| **Total** | **52** | **22** | **74** |

`pci.json` exists but contains **zero rules**, so the PCI profile is hidden in the UI.
Author it or delete it — see the roadmap's Later bucket.

**DynamoDB, ELBv2, SNS and SQS were pilots.** They were added as `tasks.json` files with
no PHP, deliberately with thin rule coverage. The point was to prove the JSON contract
handles awkward response shapes — bare-string lists, two-step list-then-describe, nested
lists, and SDK/ARN names that differ from the service name — before writing more services
against it. It does. Adding rules to them is now cheap.

> ⚠️ **Remediation lags rule coverage.** 39 of the 74 rules carry no remediation text,
> including **all 22 CIS rules**. A rule that fires without telling the user what to do is
> only half a feature. See roadmap item N-4.

---

## Top 20 Services by Audit Priority

Ranked by enterprise adoption and security impact.

| # | Service | Status |
| ---: | --- | --- |
| 1 | **IAM** | ✅ Partial (20 rules) |
| 2 | **S3** | ✅ Partial (7) |
| 3 | **EC2** | ✅ Partial (12, shared with VPC) |
| 4 | **VPC** | ✅ Partial |
| 5 | **RDS** | ✅ Partial (12) |
| 6 | **CloudTrail** | ✅ Partial (9) |
| 7 | **Lambda** | ✅ Partial (4) |
| 8 | **KMS** | ✅ Partial (4) |
| 9 | **Secrets Manager** | ❌ Not implemented |
| 10 | **CloudWatch** | ❌ Not implemented |
| 11 | **EKS / ECS** | ❌ Not implemented |
| 12 | **SNS / SQS** | ✅ Pilot (1 rule each) |
| 13 | **API Gateway** | ❌ Not implemented |
| 14 | **CloudFront** | ❌ Not implemented |
| 15 | **ELB / ALB** | ✅ Pilot (2 rules) |
| 16 | **DynamoDB** | ✅ Pilot (2 rules) |
| 17 | **Route 53** | ❌ Not implemented |
| 18 | **ACM** | ❌ Not implemented |
| 19 | **Config** | ❌ Not implemented |
| 20 | **GuardDuty** | ❌ Not implemented |

---

## Top 100 Security Issues by Category

Reference list. Rules are written against these; the numbering is stable so rules can
cite it.

### 1. Identity & Access Management — 20 issues

**High severity**
1. Root account without MFA enabled
2. Root account access keys exist
3. IAM users without MFA
4. IAM policies with wildcard (`*:*`) permissions
5. IAM users with AdministratorAccess policy
6. IAM users with console access but no MFA
7. Inactive IAM users (90+ days)
8. Access keys not rotated (90+ days)
9. IAM password policy not compliant
10. Cross-account IAM role trust relationships too permissive

**Medium severity**
11. IAM users with inline policies
12. IAM groups with inline policies
13. IAM roles with inline policies
14. IAM users not in groups
15. IAM policies attached directly to users
16. IAM users with multiple access keys
17. Console passwords not rotated (90+ days)
18. IAM roles with excessive permissions
19. Service-linked roles with overly permissive policies
20. IAM Access Analyzer not enabled

### 2. Storage Security (S3, EBS, EFS) — 15 issues

**High severity**
21. S3 buckets publicly accessible
22. S3 buckets without encryption at rest
23. S3 bucket policies allowing public access
24. S3 buckets with ACL allowing AllUsers
25. EBS volumes unencrypted
26. EBS snapshots shared publicly

**Medium severity**
27. S3 buckets without versioning
28. S3 buckets without logging enabled
29. S3 buckets without lifecycle policies
30. S3 Object Lock not enabled for compliance data
31. EFS file systems unencrypted
32. EFS without backup policy
33. S3 buckets without MFA delete enabled
34. S3 cross-region replication not enabled for DR
35. S3 bucket keys not enabled for cost optimisation

### 3. Network Security (VPC, Security Groups, NACLs) — 15 issues

**High severity**
36. Security groups allowing 0.0.0.0/0 on SSH (22)
37. Security groups allowing 0.0.0.0/0 on RDP (3389)
38. Security groups allowing 0.0.0.0/0 on all ports
39. Default VPC in use
40. VPC flow logs not enabled
41. Network ACLs allowing unrestricted inbound traffic

**Medium severity**
42. Security groups with unrestricted outbound rules
43. Subnets auto-assign public IP enabled
44. Missing NAT Gateway for private subnet internet access
45. VPC endpoints not configured for AWS services
46. Unused security groups
47. Unused Elastic IP addresses
48. VPC peering without proper route table configuration
49. Transit Gateway attachments without encryption
50. Network firewall not configured

### 4. Compute Security (EC2, Lambda, ECS/EKS) — 15 issues

**High severity**
51. EC2 instances with public IP addresses
52. EC2 instances with IMDSv1 enabled (SSRF vulnerable)
53. Lambda functions with wildcard IAM permissions
54. Lambda functions in public subnet
55. EKS cluster endpoint publicly accessible
56. ECS tasks running as root

**Medium severity**
57. EC2 instances without termination protection
58. EC2 instances using default security group
59. Lambda functions without VPC configuration
60. Lambda environment variables with secrets in plaintext
61. ECS tasks without logging enabled
62. EKS cluster logging not enabled
63. EC2 instances without detailed monitoring
64. Auto Scaling groups without health checks
65. Lambda functions with deprecated runtimes

### 5. Database Security (RDS, DynamoDB, ElastiCache) — 12 issues

**High severity**
66. RDS instances publicly accessible
67. RDS instances without encryption
68. RDS snapshots shared publicly
69. DynamoDB tables without encryption
70. ElastiCache clusters without encryption in transit

**Medium severity**
71. RDS instances without Multi-AZ
72. RDS automated backups disabled
73. RDS instances with default parameter groups
74. DynamoDB tables without point-in-time recovery
75. ElastiCache without automatic failover
76. RDS instances without enhanced monitoring
77. Aurora clusters without deletion protection

### 6. Logging & Monitoring (CloudTrail, CloudWatch, Config) — 10 issues

**High severity**
78. CloudTrail not enabled in all regions
79. CloudTrail logs not encrypted
80. CloudTrail log file validation disabled
81. CloudWatch Log Groups without retention policy

**Medium severity**
82. CloudTrail not integrated with CloudWatch
83. AWS Config not enabled
84. GuardDuty not enabled
85. Security Hub not enabled
86. CloudWatch alarms not configured for root login
87. VPC flow logs not sent to CloudWatch

### 7. Encryption & Key Management (KMS, ACM, Secrets Manager) — 8 issues

**High severity**
88. KMS keys without rotation enabled
89. KMS keys scheduled for deletion
90. Secrets Manager secrets without rotation
91. ACM certificates expiring soon (<30 days)

**Medium severity**
92. KMS keys with overly permissive policies
93. Secrets Manager without VPC endpoint
94. ACM certificates using RSA-1024
95. Customer managed keys not used for sensitive data

### 8. Application Security (API Gateway, CloudFront, ELB) — 5 issues

**High severity**
96. API Gateway without authentication
97. CloudFront without WAF
98. ALB without HTTPS listener
99. ALB using outdated TLS policy

**Medium severity**
100. CloudFront without access logging

---

## Staged Coverage Plan

Ordered by security impact against implementation cost. Stage numbering is historical —
treat it as a priority queue, not a schedule. Actual sequencing lives in
[`roadmap.md`](./roadmap.md).

### Stage 1 — Foundation Security *(high impact, simple)*

**1.1 CloudTrail** — *critical for audit compliance and incident response*
- [x] Actively logging (`getTrailStatus`)
- [x] Log encryption enabled (KMS)
- [x] Log file validation enabled
- [x] Integrated with CloudWatch
- [x] Multi-region trail
- [ ] S3 bucket not publicly accessible *(cross-resource, deferred)*

**1.2 IAM enhancements** — *prevents account compromise*
- [x] Root account MFA
- [x] Root account access keys
- [x] Password policy compliance
- [ ] Access key rotation (90+ days) *(needs credential report)*
- [ ] Inactive user detection (90+ days) *(needs credential report)*
- [ ] Console password rotation *(needs credential report)*
- [ ] IAM Access Analyzer enabled
- [ ] Policy analysis for wildcards

**1.3 S3 enhancements** — *data protection and compliance*
- [x] Bucket logging enabled
- [ ] Lifecycle policy
- [ ] MFA delete enabled
- [ ] Object Lock for compliance data
- [ ] Cross-region replication

**1.4 EBS** *(new service)* — *data protection*
- [ ] Volume encryption
- [ ] Snapshot encryption
- [ ] Snapshot public sharing
- [ ] Unused volume detection

### Stage 2 — Network & Compute *(high impact, medium)*

**2.1 VPC / network enhancements** — *prevents unauthorised access*
- [x] VPC flow logs enabled
- [ ] Security group SSH/RDP from 0.0.0.0/0
- [ ] Security group all ports from 0.0.0.0/0
- [ ] Network ACL unrestricted access
- [ ] Unused security groups
- [ ] Unused Elastic IPs
- [ ] VPC endpoint configuration
- [ ] NAT Gateway configuration

**2.2 EC2 enhancements**
- [x] IMDSv2 enforcement
- [ ] Detailed monitoring
- [ ] Using default security group
- [ ] Auto Scaling health check configuration

**2.3 Lambda** — *serverless security*
- [x] VPC configuration
- [x] Environment variable secrets *(heuristic on variable names)*
- [x] Deprecated runtime
- [x] Public function URL (`AuthType=NONE`)
- [ ] IAM role permissions
- [ ] Reserved concurrency

**2.4 RDS enhancements**
- [x] Enhanced monitoring
- [x] Deletion protection
- [x] Minor version auto-upgrade
- [ ] Using default parameter group
- [ ] Performance Insights

### Stage 3 — Encryption & Secrets *(high impact, medium)*

**3.1 KMS**
- [x] Key rotation enabled
- [x] Key deletion scheduled
- [x] Customer key disabled
- [ ] Key policy permissions
- [ ] Cross-account access

**3.2 Secrets Manager** *(new service)* — *credentials security*
- [ ] Rotation enabled
- [ ] Rotation schedule
- [ ] Secrets without recent access
- [ ] Overly permissive policies
- [ ] VPC endpoint configured

**3.3 ACM** *(new service)* — *TLS/SSL security, simple API*
- [ ] Certificate expiration (<30, <7 days)
- [ ] Validation method
- [ ] Key algorithm (RSA-2048+)
- [ ] Unused certificate detection
- [ ] Certificate transparency logging

### Stage 4 — Security Services Integration *(medium impact, medium)*

**4.1 GuardDuty** — enabled in all regions, findings severity, S3/EKS/malware protection
**4.2 AWS Config** — enabled in all regions, recording all resource types, delivery channel, rule compliance
**4.3 Security Hub** — enabled, standards enabled, findings integration, cross-region aggregation
**4.4 CloudWatch** — log group retention and encryption, plus alarms for root login, unauthorised API calls, IAM policy changes, security group changes

*Stage 4 unlocks CIS section 4 (Monitoring), which is currently at zero coverage.*

### Stage 5 — Application & Container *(medium impact, high)*

**5.1 API Gateway** — authentication, authorization, WAF, logging, throttling, TLS version
**5.2 CloudFront** — HTTPS enforcement, TLS version, WAF, access logging, origin access control, geo-restriction
**5.3 ELB / ALB** *(2 rules exist)* — HTTPS listener, TLS policy, access logging, deletion protection, WAF, NLB cross-zone
**5.4 EKS** — private endpoint, cluster logging, secrets encryption, node groups, pod security *(very high complexity)*
**5.5 ECS** — task definition secrets, execution role, Container Insights, network configuration, Fargate platform version

### Stage 6 — Messaging & Data *(medium impact, medium)*

**6.1 SNS** *(1 rule exists)* — topic encryption, cross-account policy, HTTPS delivery, subscription protocol
**6.2 SQS** *(1 rule exists)* — queue encryption, cross-account policy, dead letter queue, VPC endpoint
**6.3 DynamoDB** *(2 rules exist)* — encryption, point-in-time recovery, deletion protection, auto-scaling, stream encryption
**6.4 ElastiCache** *(new service)* — encryption at rest and in transit, automatic failover, Redis auth token, automatic backup

*The three pilot services here are the cheapest wins on the board — the plumbing is done,
only rules are missing.*

### Stage 7 — Advanced *(medium impact, high)*

**7.1 EFS** — encryption at rest and in transit, backup policy, lifecycle policy, access points
**7.2 ECR** — image scanning, encryption, lifecycle policy, repository policy, immutable tags
**7.3 Route 53** — DNSSEC, health checks, query logging, resolver DNSSEC validation
**7.4 Cognito** — MFA configuration, password policy, advanced security, unauthenticated identities, WAF
**7.5 Redshift** — encryption, public accessibility, SSL enforcement, audit logging, automated snapshots

> Note: a recommendation in `tips.json` already references `tops-route53-001`, a rule that
> does not exist. Either write the Route 53 scanner or remove the orphan.

---

## CIS AWS Foundations Benchmark v5.0 Alignment

`cis.json` holds 22 checks, selectable as the **CIS** scan profile. It covers what's
evaluable from currently collected data; controls needing data we don't collect are
deferred.

| CIS section | Controls covered | Coverage | Blocked on |
| --- | --- | --- | --- |
| 1. IAM | 1.4, 1.5, 1.8, 1.9, 1.14, 1.15, 1.16 | Partial | Credential report for 1.7 / 1.10 / 1.12 |
| 2. Storage | 2.1.1, 2.1.4, 2.3.1, 2.3.2, 2.3.3 | Partial | EBS / EFS scanners (Stage 1.4, 7.1) |
| 3. Logging | 3.1, 3.2, 3.4, 3.7, 3.8, 3.9 | Partial | Cross-resource checks / AWS Config for 3.3, 3.5, 3.6 |
| 4. Monitoring | — | **None** | CloudWatch metric filters and alarms (Stage 4.4) |
| 5. Networking | 5.1, 5.2, 5.3, 5.4 | Partial | Peering route inspection for 5.5 |

**Three unlocks would move CIS coverage most:** the IAM credential report (section 1),
CloudWatch alarms (all of section 4), and an EBS scanner (section 2).

---

## Effort Summary

| Stage | New services | New checks | Rough size |
| --- | --- | ---: | ---: |
| 1 | 1 (EBS) | ~25 | 2 sprints |
| 2 | — | ~25 | 2 |
| 3 | 2 (Secrets Manager, ACM) | ~20 | 2 |
| 4 | 4 (GuardDuty, Config, Security Hub, CloudWatch) | ~25 | 2 |
| 5 | 4 (API GW, CloudFront, EKS, ECS) | ~30 | 4 |
| 6 | 1 (ElastiCache) | ~20 | 2 |
| 7 | 5 (EFS, ECR, Route 53, Cognito, Redshift) | ~25 | 4 |

**~170 additional checks** across **17 more services**. Sprint figures assume a dedicated
developer and should be read as relative sizing, not commitments — see the roadmap's
capacity constraint.

### Cheapest wins available now

1. **Add rules to the four pilot services** (DynamoDB, ELBv2, SNS, SQS) — plumbing done, rules missing
2. **Write remediation text for the 22 CIS rules** — no scanning work at all, and closes the biggest user-facing gap
3. **EBS scanner** — simple API calls, unlocks CIS section 2
4. **ACM scanner** — simple API calls, high signal (expiring certificates)

---

## Architecture

**Adding a service does not require PHP.** Create one file —
`app/rules/tasks/{service}/tasks.json` — and validate it:

```bash
php artisan scan:validate-rules
```

Its `config` block declares everything the app needs: the SDK client key, whether the
service is regional, which scan profiles include it, and where its items live in each
response. `ServiceRegistry` discovers it from there, and `GenericAwsScanner` validates
every method name against the AWS SDK's own API model and walks paginated operations to
the end. Rules go in `app/rules/rulesets/*.json` and carry their own remediation text.

**The full contract — including the three item shapes and the two conditions traps — is
in `app/rules/README.md`. Read that before adding a service or a rule.**

A service needs a PHP class only when the SDK model cannot express its behaviour, which
so far means two: `S3Scanner` (resolve each bucket's region before signing) and
`IamScanner` (treat a missing password policy as the finding rather than an error). Both
subclass `GenericAwsScanner` and override a single method; a service opts in by naming the
class in its `config.scanner`.

```
ProcessAuditScanJob (orchestrator: global services inline, region jobs fanned out)
    └── ProcessRegionScanJob (per service, per region)
            └── RulesEngine (reads tasks.json, walks items, stores scan_details)
                    └── GenericAwsScanner (any AWS service, from the SDK API model)
                            ├── S3Scanner    (bucket region resolution)
                            └── IamScanner   (absent password policy)
            └── FindingsEngine (reads rulesets, writes scan_results)
                    └── ConditionEvaluator
```

### Known constraint: rule conditions use `eval()`

`ConditionEvaluator.php:40` evaluates rule conditions with PHP `eval()`. This is currently
acceptable — every ruleset in the repository is written by us, and a self-hosted operator
editing their own rules already controls the server the code runs on.

**It stops being acceptable the moment rulesets are shared.** An open-source project
invites exactly that: community rulesets, downloaded rules, a "paste your custom rule"
field. Any of those turns a rule condition into remote code execution.

Options, cheapest first:
1. **Whitelist validation** — pre-validate conditions against allowed functions and operators
2. **Expression language** — replace `eval()` with Symfony ExpressionLanguage or similar
3. **AST parsing** — parse to a syntax tree and validate against allowed operations
4. **Templates** — offer predefined condition templates rather than free expressions

This must be resolved **before** shipping any feature that accepts a ruleset we did not
write. It is tracked in the roadmap's Later bucket; the trigger is community rulesets, not
a date.
