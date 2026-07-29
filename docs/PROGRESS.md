# Application Progress

This document tracks feature completion status, practices compliance, and development
priorities. It is a record of **what is actually in the code**, verified against the
codebase — not a record of intent. If a claim here is not backed by a file you can open,
it does not belong in this document.

**Last Updated:** July 29, 2026 (full code audit against commit `0f31ba5`)

> The previous revision was dated January 11, 2026 and had drifted badly — roughly eight
> merged PRs of work were missing, and several shipped features were still listed as
> "missing". This revision was rebuilt from the code.

## Product Direction

**TOPS is an open-source, self-hosted product.** The default deployment is Docker Compose
on the operator's own hardware. Hosted multi-tenant SaaS is not the current focus.

Two capabilities are **built but deliberately parked**, and must remain available as
opt-in options for self-hosted operators rather than being removed:

| Capability | State | Direction |
| --- | --- | --- |
| **Firebase Authentication** | Built and complete | **Not in use.** Off by default in Docker (`FIREBASE_USER_AUTH=false`). Stays in the codebase as an opt-in for operators who want Firebase/OAuth. The default path must never require a Firebase project. |
| **MFA** (TOTP + email OTP) | Partially built | **Not in use.** Wanted soon, but deliberately gated until the roadmap is agreed. Must also be opt-in, and must be reachable without Firebase before it can ship. |

The test that governs new work: **can a stranger clone this repo and run it on their own
box without an AWS account or a Firebase project?**

## Architecture Overview

**Stack**: Laravel 11/12 (PHP 8.2+) + Vue 3 + TypeScript + Inertia.js + Tailwind CSS

- **Backend**: Laravel monolith, MySQL (SQLite `:memory:` for tests)
- **Frontend**: Vue 3 + TypeScript + Inertia.js
- **Authentication**: two paths behind the `FIREBASE_USER_AUTH` flag — native Laravel
  (Breeze) session auth, or Firebase. See `app/config/features.php`.
- **Queues**: Laravel queues; SQS in the hosted setup, `database` driver self-hosted
- **Scanning**: JSON-driven — a service is added by writing `tasks.json`, no PHP
- **Testing**: PHPUnit (unit/feature) + Playwright (E2E), CI on GitHub Actions

---

## Feature Completion Status

### ✅ Completed

#### Authentication (dual-path)
- **Status**: ✅ Complete
- **Implementation**:
  - `FIREBASE_USER_AUTH` feature flag — `app/config/features.php:14`, shared to the
    frontend via `HandleInertiaRequests.php:75-77`, consumed by `useFeatures.ts`
  - **Native Laravel path** (default for self-hosted): full Breeze controller set in
    `app/app/Http/Controllers/Auth/` — login, register, password reset, email
    verification, password confirmation
  - **Firebase path** (opt-in): `FirebaseAuthController`, `VerifyFirebaseToken`
    middleware, `EnsureFirebaseAuthEnabled` middleware (404s Firebase routes when off),
    `useFirebase.ts`
  - Defaults differ by deployment: code default is `true` (`app/.env.example:97`),
    Docker default is `false` (`.env.docker.example:88`, `docker-compose.yml:72,113`)
- **Caveats**:
  - **OAuth (Google/GitHub/Microsoft) only works on the Firebase path** — it is Firebase
    `signInWithPopup` (`useFirebase.ts:90-139`). With the self-hosted default there is
    no OAuth at all. Apple is not wired.
  - `Auth/OAuthController.php` (Socialite) is **dead code** — zero routes reference it,
    and `laravel/socialite` is an unused dependency.
  - The flag is baked into the frontend bundle at build time; changing it needs a
    rebuild (`docker-compose.README.md:125`).
- **Tests**: 32 feature tests across `tests/Feature/Auth/` + middleware tests

#### Organizations & Multi-Tenancy
- **Status**: ✅ Complete
- **Implementation**: org CRUD (`OrganizationsController`), `SetOrganizationContext`
  middleware applied to all web and API routes, org switching via cookie + localStorage
  (`useOrganizations.ts`), frontend at `Pages/Organizations/`
- **Tests**: `OrganizationsControllerTest` (17), `SetOrganizationContextTest` (15),
  `OrganizationModelTest` (11)

#### Team Management & RBAC
- **Status**: ✅ Complete *(shipped since the last revision; was entirely unrecorded)*
- **Implementation**:
  - Four roles — owner, administrator, auditor, viewer
    (`create_organization_members_table`)
  - Invitations with token + expiry (`create_organization_invitations_table`),
    plus a data migration backfilling existing owners
  - `OrganizationPermission` service — 15 capability methods
  - `OrganizationMembersController` — invite, update role, remove, list/cancel
    invitations, transfer ownership, accept invitation
  - Notifications: `InviteMemberNotification`, `MemberJoinedNotification`,
    `OrganizationInvitation`
  - UI: `TeamManagement.vue`, `InviteMemberModal.vue`, `TransferOwnershipModal.vue`,
    `AcceptInvitation.vue`; composables `useOrganizationMembers`,
    `useOrganizationPermissions`
- **Tests**: 51 tests (`OrganizationMembersControllerTest` 34,
  `OrganizationPermissionTest` 7, model tests 10) + `e2e/organizations.spec.ts` (13)
- **Known inconsistency**: `OrganizationMembersController.php:189,245` carry comments
  saying "only owner" while the code permits administrators
  (`OrganizationPermission::canManageMembers()`). The plan doc agrees with the comments,
  not the code. Needs a decision, then align all three.

#### AWS Account Onboarding
- **Status**: ✅ Complete *(previously listed as partial with three open next-steps —
  all three were already done)*
- **Implementation**: CloudFormation quick-create URL generation with config guard and
  actionable errors, pending-account de-duplication, SNS callback handler, SQS polling
  (`aws:process-sqs`) plus DLQ redrive, role ARN encryption at rest
  (`AwsAccount.php:53-70`), 5-second UI status polling
  (`useAwsAccounts.ts:162-166`), manual entry fallback
- **Tests**: 51 tests (`AwsAccountsControllerTest` 28, `AwsAccountModelTest` 9,
  `ProcessSqsMessagesTest` 8, `RedriveDlqMessagesTest` 6)
- **⚠️ Security gap — see Known Issues.** SNS signature verification is a stub.

#### Scanning Engine
- **Status**: ✅ Complete
- **Implementation**:
  - **JSON-driven architecture** — adding a service is a `tasks.json` change with no
    PHP (`ServiceRegistry`, `GenericAwsScanner`). The per-service scanner classes
    (CloudTrail, EC2, KMS, Lambda, RDS) were deleted in `eac9de5`. Only `IamScanner`
    and `S3Scanner` remain, each overriding a single method.
  - **11 services** with `tasks.json`: cloudtrail, dynamodb, ec2, elbv2, iam, kms,
    lambda, rds, s3, sns, sqs
  - **74 authored rules** — `basic.json` 52, `cis.json` 22, `pci.json` **0**
  - Scan profiles (basic / CIS / PCI) via `ScanProfilesService`; a profile with no
    rules is hidden from the UI, so **PCI is currently invisible**
  - `scan:validate-rules` command, wired into CI
  - Region fan-out (`ProcessAuditScanJob` → `ProcessRegionScanJob`), stale-scan sweeper
  - Real-time scan status via 5s polling (`useScans.ts:271-275`)
- **Tests**: `RulesEngineTest` (25), `ScanModelTest` (24), `ScansControllerTest` (29),
  `NewServiceRulesTest` (16), `FindingsEngineTest` (13), `PilotServiceRulesTest` (13),
  `e2e/scans.spec.ts` (31), and more

#### Findings
- **Status**: ✅ Complete *(shipped since the last revision; was unrecorded)*
- **Implementation**: org-wide findings list and per-finding-type detail page
  (`/findings`, `/findings/{findingType}`), `FindingsController` (index, byType, show,
  update, recommendations), status updates (open/resolved/ignored) with API **and** UI
  (`Findings/Index.vue:94-100,225-240`), filtering
- **Tests**: `FindingsControllerTest` (22)

#### Dashboard
- **Status**: ✅ Complete with real data *(previously listed as "UI exists, no data")*
- **Implementation**: `Dashboard.vue` pulls live account count, active scans, critical
  findings, compliance score and recent scans from the findings/scans/accounts composables

#### Insights
- **Status**: ✅ Complete *(shipped since the last revision; was unrecorded)*
- **Implementation**: `InsightsController` (trend bucketing, key insights, per-framework
  compliance), `ComplianceScoreService`, `FindingsTrendChart` and `SeverityDonutChart`
  components, `Insights/Index.vue`
- **Tests**: `InsightsControllerTest` (12)

#### Self-Hosted Docker Stack
- **Status**: ✅ Complete for the app tier *(unrecorded; see
  `docs/sessions/2026-06-21-docker-self-hosted-deployment.md`)*
- **Implementation**: `docker-compose.yml` (mysql, maildev, app, worker, backup,
  profile-gated db-restore), app Dockerfile + entrypoints + supervisord + nginx,
  `.env.docker.example`, `docker-compose.README.md`, `install.sh` for the AWS
  messaging tier

#### Database Backup & PITR
- **Status**: ✅ Complete, with its own test suite *(unrecorded)*
- **Implementation**: `docker/backup/` (backup + restore binaries, shared lib, tests),
  `backup.sh`, MySQL binlog config, `docs/backups.md`

#### CI Pipeline
- **Status**: ✅ Complete *(unrecorded)*
- **Implementation**: `.github/workflows/tests.yml` — two jobs: PHPUnit +
  `scan:validate-rules`, and a frontend job running `npm ci` (no flags) + `npm run build`

---

### 🔄 Partial

#### MFA — parked, see Product Direction
- **Status**: 🔄 Partial, **not in use**
- **Built**: `MfaApiService`, `FirebaseAuthController::verifyMfa()` /
  `requestEmailOtp()`, email OTP fallback fully in-app (6-digit, cache-backed, 10-min
  expiry), `MfaSection.vue`, `MfaAlertBanner.vue`, `useMfa.ts`; 28 tests
- **Blocking gaps for the self-hosted product**:
  1. **TOTP depends on an external "Teem OTP API"** that is not in this repository
     (`config/services.php:84-85`, default `http://127.0.0.1:8787`). Only an OpenAPI
     reference exists under `references/mfa/`.
  2. **MFA is reachable only on the Firebase path** — the routes are gated by
     `EnsureFirebaseAuthEnabled`, so with the self-hosted default there is no MFA at
     all. No `two_factor` columns exist in any migration.
  3. `useMfa.ts` calls the OTP API **directly from the browser**, not proxied through
     Laravel.
- **Decision needed before this restarts**: whether to implement TOTP natively in
  Laravel (removing the external dependency) or ship the OTP service as part of the
  open-source stack.

#### Remediation Recommendations
- **Status**: 🔄 Partial *(unrecorded)*
- **Built**: `rules/recommendations/tips.json`, `RecommendationsLoader`,
  `GET /api/recommendations`, rendered in `Findings/Show.vue`
- **Gap**: remediation exists in two layers and both are thin.

  | Layer | Coverage | Notes |
  | --- | --- | --- |
  | One-line `remediation` field on the rule itself (`rulesets/*.json`) | **35 of 74** | All 17 uncovered basic rules **and all 22 CIS rules** have none |
  | Rich recommendation — steps, links, impact (`recommendations/tips.json`) | **10 of 74** | 8 recommendations, grouped for the remediation workflow |

  The CIS ruleset is the single biggest hole: 22 rules, zero remediation text of either
  kind. One recommendation targets `tops-route53-001`, a rule that does not exist.

#### Compliance Rulesets
- **Status**: 🔄 Partial
- **Built**: `basic.json` (52), `cis.json` (22 CIS AWS Foundations v5.0 checks)
- **Gap**: `pci.json` is empty six months after being created; the profile is hidden.
  CIS sections 4 (Monitoring) and parts of 1/2/3 need data we don't yet collect.

---

### ❌ Not Started

| Feature | Priority note |
| --- | --- |
| **Report export (PDF/CSV/JSON)** | No controller, route or UI exists |
| **Scheduled / recurring scans** | No table, model or UI. The one scheduled command is a stale-scan sweeper, not user-facing |
| **Multi-cloud (Azure, GCP)** | Untouched; AWS-only |
| **Open-source project hygiene** | See below — this is the pivot's critical path |

---

## Open-Source Readiness

New section, reflecting the product direction. **Nothing in this list exists yet**, and
most of it blocks the first external contributor or self-hoster.

### Blockers

1. ~~**No `LICENSE` file.**~~ ✅ **Resolved 2026-07-29** — Apache-2.0, with trademark held
   separately (`TRADEMARK.md`) and a DCO for contributions (`CONTRIBUTING.md`). Rationale
   in roadmap decision D-7. `composer.json` had been declaring the project MIT under the
   Laravel skeleton's name; corrected in the same change.
2. ~~**`npm ci` fails on a clean checkout.**~~ ✅ **Resolved 2026-07-29** —
   `@vitejs/plugin-vue` moved to `^6`, which peer-depends on vite `^5 || ^6 || ^7`. A
   frontend CI job now runs `npm ci` with no flags plus `npm run build`, so a regression
   fails the build rather than being absorbed by `--legacy-peer-deps`.
3. **AWS account onboarding requires vendor infrastructure.** `init()` hard-fails 503
   without `AWS_PARENT_ACCOUNT_ID`, `TOPS_CFN_TEMPLATE_URL` and
   `TOPS_DEPLOYMENT_REGION`, and `install.sh` deploys real SNS/SQS into an account you
   control. A self-hoster cannot connect an AWS account without doing this first.
4. **Vendor-owned defaults baked into `.env.example`** — the CloudFormation template URL
   points at `storage.teemops.com` (`:88`), and a Teemops AWS account ID plus SQS ARNs
   are hard-coded (`:89-93`).
5. **MFA's TOTP backend is a closed external service** (see above).

### Missing project hygiene

- `CONTRIBUTING.md` now exists but covers licensing and DCO sign-off only. No
  `CODE_OF_CONDUCT.md` and no issue templates — `.github/` still contains only
  `pull_request_template.md` and `workflows/tests.yml`. The fuller contributor
  documentation belongs with the public-release milestone.
- **No DCO enforcement in CI.** Sign-off is documented but nothing rejects an unsigned
  commit, so the guarantee behind D-7 depends on reviewer diligence.
- `README.md:31-36` still reads as hosted SaaS — it lists a Firebase project and an AWS
  account as hard prerequisites and points at `app/README.md` rather than the Docker path.
- No published container images; Compose builds from source only.
- `~/.aws` is mounted read-only into the app and worker containers
  (`docker-compose.yml:82,124`), assuming host AWS credentials exist.

### Not blocking (verified working self-hosted)

- **Queues** run on the `database` driver end to end (`.env.docker.example:74-76`), with
  a clear 503 naming `SCAN_QUEUE_CONNECTION=database` when misconfigured.
- **Auth** runs natively without Firebase; this is the Docker default.

---

## Known Issues & Technical Debt

### High — security

1. **SNS signature verification is not implemented.**
   `app/app/Services/SnsSignatureVerifier.php:62-66` is an explicit `TODO`. `verify()`
   checks only the `x-amz-sns-message-type` header and the presence of three JSON
   fields; `verifyWithAwsSdk()` (`:76-93`) is a comment-only shell that delegates
   straight back to `verify()`. `SubscriptionConfirmation` messages are auto-accepted.
   The callback route is unauthenticated by design (`api.php:77`), so **anyone who can
   POST a well-formed body can attempt to register a role ARN.** The previous revision
   of this document claimed this feature was complete.

### Medium

2. **Remediation coverage is thin** — 39 of 74 rules carry no remediation text at all
   (including every CIS rule), and only 10 have rich step-by-step guidance.
3. **`pci.json` is empty** — the profile is dead weight until authored.
4. **`Auth/OAuthController.php` is dead code** with an unused `laravel/socialite`
   dependency. Delete it or route it.
5. **Member-management permissions contradict themselves** across code, comments and the
   plan doc.
6. **No test asserts the app boots and authenticates with `FIREBASE_USER_AUTH=false`**
   end to end — which is the self-hosted default path. `EnsureFirebaseAuthEnabledTest`
   (2 methods) is the only flag coverage.

### Low

7. The `firebase` npm package is always bundled, even when the flag is off.
8. `install.sh` and `docker/installer/` have no tests.

---

## Test Coverage

**55 PHP test files, ~537 test methods**, plus 52 Playwright E2E specs.

| Area | Coverage |
| --- | --- |
| Scanning & rules engine | ✅ Strong |
| Organizations, members, RBAC | ✅ Strong (51 tests) |
| AWS accounts & SQS jobs | ✅ Strong (51 tests) |
| Findings | ✅ Good (22 tests) |
| Insights | ✅ Good (12 tests) |
| Middleware | ✅ Good |
| Auth (both paths) | ✅ Good (32 feature tests) |
| Self-hosted / native-auth boot path | ❌ None |
| Docker stack | ⚠️ Shell tests for backup/PITR only |
| Frontend build | ✅ CI job — `npm ci` + `npm run build` |

E2E: `scans.spec.ts` (31), `organizations.spec.ts` (13), `mfa.spec.ts` (6),
`auth.spec.ts` (2).

---

## Practices Compliance

| Practice | Status |
| --- | --- |
| **Product** — simplicity first, MVP focus | ✅ Following |
| **Architecture** — monolith, clear boundaries, no premature optimisation | ✅ Following. The JSON-driven scanner refactor is a strong example: adding a service went from a PHP class to a config file |
| **Database** — normalised schema, reversible migrations, Eloquent only | ✅ Following |
| **Code quality** | ✅ Following |
| **Security** — multi-tenancy, authz, encryption | ⚠️ Org scoping and encryption are solid; **the SNS webhook is the outstanding gap** |
| **Testing** — tests ship with the change | ✅ Following for application code; the frontend build is now covered by CI; ❌ the self-hosted boot path is still untested |
| **Documentation** | ⚠️ This document had drifted six months. Update it with each merged feature, not in batches |

---

## Immediate Priorities

Ordered against the open-source self-hosted direction, not against feature count.

1. ~~**Add a `LICENSE` file.**~~ ✅ Done 2026-07-29 — Apache-2.0.
2. ~~**Fix `npm ci` on a clean checkout**, then add a frontend build job to CI.~~
   ✅ Done 2026-07-29.
3. **Rewrite `README.md` for the self-hosted path**, removing Firebase and AWS from the
   hard prerequisites.
4. **Remove vendor-owned defaults** from `.env.example`.
5. **Implement SNS signature verification** — the one genuine security gap.
6. **Decide the MFA approach** (native TOTP vs shipping the OTP service) before any MFA
   work restarts — per the product direction, this is gated on the roadmap.
7. **Close the remediation coverage gap** — findings without remediation text are
   findings users can't act on. The CIS ruleset has none at all.

Items 1–4 are the ones a stranger hits before they can use or contribute to the project.
Ordering matches the roadmap: SNS verification sits behind the setup docs because each
design partner runs their own instance and their own topic (roadmap X-2).

> **See [`roadmap.md`](./roadmap.md)** for how these are sequenced into Now / Next /
> Later, and for the user stories behind each one. This document records *state*; the
> roadmap records *plan*.

---

## Maintaining This Document

Update it in the PR that ships the feature. Every status claim should cite a file. When
a feature is listed as complete, the "next steps" for it must be deleted — the previous
revision contradicted itself by listing the same work as both done and outstanding.
