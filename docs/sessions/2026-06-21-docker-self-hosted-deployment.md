# Session: Docker Compose Self-Hosted Deployment

**Date:** 2026-06-21  
**Topic:** Deployment strategy, Docker Compose Phase 1–2, Firebase feature flag, AWS messaging installer  
**Source transcript:** Cursor agent session `7a2bfe8a-537f-4394-83b3-816cc103477c` (local path: `~/.cursor/projects/home-ben-dev-saas/agent-transcripts/7a2bfe8a-537f-4394-83b3-816cc103477c.jsonl`)

This document captures the full arc of the conversation: questions asked, decisions made, what was built, and what remains on the roadmap.

---

## 1. Conversation timeline (user prompts)

| # | Topic |
|---|--------|
| 1 | Assess ECS vs Lambda split for `@app`; ECS Free Tier? |
| 2 | Pivot to single-server monolith; focus on AWS Marketplace / open source, not SaaS-first. Three customer workflows: git+docker-compose, EC2/ALB CFN install, AWS Marketplace. |
| 3 | SNS cross-region: can child CFN custom resource notify parent SNS in one pinned region instead of StackSet per region? |
| 4 | Commit to **docker-compose only** for now: no LocalStack, no RDS, no Marketplace. Pin `TOPS_DEPLOYMENT_REGION`, installer container for SAM/SNS/SQS, `install.sh` with local vs AWS target, AWS creds from `~/.aws` or EC2 instance profile. |
| 5 | Child CFN template must be uploaded via SAM/installer (not `storage.teemops.com`). Approve phased plan; **implement Phase 1** and test. |
| 6 | Exclude `app/.env` from Docker image build. |
| 7 | Fix redirect to port 80 when app runs on `:8080`. |
| 8 | Add `FIREBASE_USER_AUTH` feature flag for Docker (Laravel Breeze when off). |
| 9 | Organizations page error: Kreait Firebase init with empty credentials when flag off. |
| 10 | **Phase 2:** installer container + `core-docker` SAM + `generated/teemops.env`. |
| 11 | S3 deploy bucket name collision across accounts — include account ID in bucket name. |
| 12 | Audit hardcoded `ap-southeast-6`; wire deployment region for “Add AWS account” UI. |
| 13 | Export this conversation to the repo. |

---

## 2. Strategic decisions

### Product direction

- **Not SaaS-first.** Primary paths: self-hosted / open source, AWS Marketplace (later), customer-owned data.
- **Monolith preserved.** Laravel 12 + Vue 3 + Inertia stays one codebase; no Lambda/API split for v1.
- **Install simplicity** is the selling point for EC2/Marketplace customers (MSP / IT services use case).

### Deployment architecture (agreed)

| Path | Description |
|------|-------------|
| **Dev / git** | Clone repo, `docker-compose.yml`, optional `./install.sh` for AWS messaging |
| **Production EC2** | EC2 + ALB via existing CFN (Phase 4+); docker on EC2 |
| **AWS Marketplace** | Phase 5+ |

### ECS vs Lambda assessment (initial question)

- **ECS/Fargate:** Lift-and-shift feasible; existing supervisor/queue patterns map to sidecar tasks. Free Tier: limited (≈20 GB-hours/month Fargate for 12 months for new accounts); not a long-term free production path.
- **Lambda split:** Would require removing Inertia SSR, re-architecting auth/sessions, and splitting queue workers — high effort, rejected for current direction.

### SNS / region simplification

- CloudFormation custom resources **can** publish to an SNS topic in **any region**; the `ServiceToken` ARN must include the **parent deployment region**, not the child stack’s region.
- **v1:** Single SNS + SQS in `TOPS_DEPLOYMENT_REGION` only. Multi-region StackSet deferred to Phase 5.
- Child template parameter **`ParentDeploymentRegion`** added so customer stacks in any region still callback to parent SNS correctly.

### Docker Compose scope (Phase 1–2)

- MySQL, Maildev, app (nginx + PHP-FPM), worker — all in compose.
- Queues: **database** for Laravel default jobs until installer runs; **SQS** for scan + account-linking after Phase 2.
- Real AWS used for messaging (not LocalStack).

---

## 3. Phased delivery plan (Roadmap 1–6)

This is the **docker-compose install roadmap** agreed when scoping Phase 1. It superseded the earlier 4-phase “monolith packaging” sketch (dev → production CFN → updates → Marketplace) for day-to-day delivery; Marketplace and update tooling are folded into later phases below.

| Phase | Status | Deliverable | Outcome |
|-------|--------|-------------|---------|
| **1** | **Done** | `docker-compose.yml`, `docker/app/*`, MySQL, maildev, worker, `.env.docker.example`, `prepare-build.sh`, `FIREBASE_USER_AUTH` flag | App runs locally **without AWS** |
| **2** | **Done** | `core-docker` SAM (SQS + S3), installer container, `install.sh`, `generated/teemops.env`, SNS in pinned region, child template upload | One-command **AWS messaging** in `TOPS_DEPLOYMENT_REGION` |
| **3** | Planned | Pin CFN URL region; S3-hosted child template fully wired; end-to-end “Add AWS account” hardened | **Add AWS account** works reliably for self-hosted (partially done in Phase 2 upload + `ParentDeploymentRegion`) |
| **4** | Planned | `core-aws` SAM (VPC + IAM + EC2/ALB), docker on EC2, `TOPS_INSTALL_TARGET=aws` path in `install.sh` | **AWS-hosted production** path (EC2 + ALB, no RDS yet) |
| **5** | Planned | StackSet multi-region SNS (optional `TOPS_SNS_REGIONS=all\|list` flag) | Child CFN stacks in **any region** can notify parent |
| **6** | Planned | `ENABLE_RDS=true` path, `infra/cloud-stack/db/` wired into installer | **Managed MySQL (RDS)** instead of compose MySQL |

### Phase details

**Phase 1 — Docker app stack**
- Monolith in compose: nginx + PHP-FPM, MySQL, Maildev, database queue worker.
- Host builds `vendor/` + `public/build/` before image build.
- No LocalStack, no RDS, no Marketplace.

**Phase 2 — AWS messaging installer**
- `TOPS_DEPLOYMENT_REGION` pins SAM/SNS/SQS to one region.
- Installer writes `generated/teemops.env`; app/worker load it.
- S3 bucket: `{env}-{accountId}-tops-deploy`; child CFN template uploaded to `templates/`.
- Worker switches to SQS consumers when `TOPS_SQS_ARN` is set.

**Phase 3 — Child account onboarding**
- `TOPS_CFN_TEMPLATE_URL` from customer bucket (not `storage.teemops.com`).
- CloudFormation quick-create passes `ParentDeploymentRegion`.
- App validates messaging config before init; clear error if install not run.

**Phase 4 — EC2 / ALB production**
- Split from `core-docker`: `core-aws` adds VPC, IAM EC2 role, EC2+ALB CFN.
- `install.sh` “AWS” target: full SAM minus RDS; UserData runs docker compose on EC2.
- ALB health check `/health`; security group → container port 80.

**Phase 5 — Multi-region SNS (optional)**
- Today: single SNS in deployment region only (v1 simplicity).
- Later: StackSet or region list for MSPs with child stacks in many regions.

**Phase 6 — RDS**
- Optional `ENABLE_RDS=true` in installer.
- Compose app/worker point at RDS instead of `mysql` service.

### Not in phases 1–6 (separate track)

- **Software updates** for self-hosted (`teemops-update.sh`, versioned releases, migration changelog) — was “Phase 3” in the earlier monolith analysis doc.
- **AWS Marketplace listing** — was “Phase 4” in that earlier sketch; deferred until EC2 install path is stable.

---

## 4. Phase 1 — implemented

### Files (main)

```
docker-compose.yml
docker-compose.README.md
.env.docker.example
.dockerignore
docker/
  app/Dockerfile
  app/entrypoint.sh
  app/worker-entrypoint.sh
  app/nginx.conf, default-site.conf, supervisord.conf, php-overrides.ini
  scripts/prepare-build.sh
```

### Behaviours

- Host runs `prepare-build.sh` (composer + npm build) before `docker compose build` (Composer in Docker build was unreliable).
- Entrypoint: wait MySQL, copy `.env.example` if needed, migrate, supervisord (nginx + php-fpm).
- **`app/.env` excluded** from image via `.dockerignore` + Dockerfile `RUN rm -f .env`.
- **Port redirect fix:** nginx `HTTP_HOST $http_host`; entrypoint syncs `APP_URL` from compose.
- **Health verified:** `/health`, `/up`, `/login`, migrations, redirect to `http://localhost:8080/login`.

### Firebase feature flag (`FIREBASE_USER_AUTH`)

- `app/config/features.php` — `firebase_auth` from env.
- Docker default: `false` in compose + `.env.docker.example`.
- Middleware `EnsureFirebaseAuthEnabled` on Firebase auth routes.
- Inertia shares `features.firebase_auth`; frontend login/register/forgot-password use Breeze when off.
- `bootstrap.ts` skips Firebase token interceptor when meta `teemops-firebase-auth` is `0`.

### Firebase / API fix (organizations page)

**Problem:** `VerifyFirebaseToken` constructed Kreait Firebase in `__construct()` on every `/api/*` request → crash with empty credentials when flag off.

**Fix:**

- Lazy-init Firebase only when `features.firebase_auth` true **and** bearer token present; otherwise session auth only.
- `$middleware->statefulApi()` in `bootstrap/app.php` so SPA session cookies work on `/api/*`.
- Safe session logging when session store not set.

---

## 5. Phase 2 — implemented

### Files (main)

```
install.sh
docker-compose.install.yml
generated/teemops.env          # gitignored output
generated/install.log          # gitignored
docker/installer/
  Dockerfile
  entrypoint.sh
  scripts/install-messaging.sh
infra/cloud-stack/core-docker/
  template.yaml
  sqs.cfn.yaml
  samconfig.toml
```

### Installer flow

1. Read `TOPS_DEPLOYMENT_REGION` from root `.env` (prompt if missing).
2. Run `installer` container with `~/.aws` mounted (or `TOPS_INSTALLER_NETWORK=host` on EC2 for IMDS).
3. `sam deploy` **core-docker** (SQS + S3 bucket).
4. `aws cloudformation deploy` **SNS** (`stackset/sns.topic.cfn.yaml`) in deployment region.
5. Upload `templates/iam.role.child.account.cfn.yaml` to `{env}-{accountId}-tops-deploy/templates/`.
6. Write **`generated/teemops.env`** with queue ARNs, account ID, region, `TOPS_CFN_TEMPLATE_URL`, scan queue connections.

### Compose integration

- `app` and `worker` load `generated/teemops.env` via `env_file`.
- `~/.aws` mounted at `/var/www/.aws` for SQS/API calls.
- Worker: if `TOPS_SQS_ARN` set, supervisord runs database worker + `aws:process-sqs` + SQS audit workers.

### S3 bucket naming

Global S3 namespace collision fix:

```
{Environment}-{AWS::AccountId}-tops-deploy
```

Example: `test-848310106659-tops-deploy`

Bucket policy: public `GetObject` on `templates/*` only (for CFN quick-create).

---

## 6. Region / “Add AWS account” wiring

### Removed or replaced hardcoded `ap-southeast-6`

| File | Change |
|------|--------|
| `infra/cloud-stack/core/samconfig.toml` | Default region → `us-east-1` |
| `infra/cloud-stack/db/samconfig.toml` | Default region → `us-east-1` |
| `infra/cloud-stack/stackset/sns.topic.cfn.yaml` | `SQSRegion` required (no default) |
| `infra/cloud-stack/deploy/install-stack.sh` | Requires `TOPS_DEPLOYMENT_REGION` or `AWS_DEFAULT_REGION` |
| `infra/cloud-stack/app/samconfig.toml` | Default region → `us-east-1` (parameter_overrides still contain account-specific NZ resource IDs) |

### App runtime

- `config/services.php`: `deployment_region` and `region` prefer **`TOPS_DEPLOYMENT_REGION`**, then `AWS_DEFAULT_REGION`.
- `AwsAccountsController::init()`:
  - Validates messaging config before creating pending account.
  - CloudFormation quick-create URL includes `region=` and `param_ParentDeploymentRegion=`.
  - Returns `503` with clear message if `./install.sh` not run.
- Child template `templates/iam.role.child.account.cfn.yaml`: `ServiceToken` uses **`ParentDeploymentRegion`**, not `AWS::Region`.
- `entrypoint.sh` syncs AWS-related env vars from compose into container `.env`.

### Key env vars (after install)

```env
TOPS_DEPLOYMENT_REGION=us-west-2
AWS_DEFAULT_REGION=us-west-2
AWS_PARENT_ACCOUNT_ID=848310106659
TOPS_CFN_TEMPLATE_URL=https://test-848310106659-tops-deploy.s3.us-west-2.amazonaws.com/templates/iam.role.child.account.cfn.yaml
TOPS_SQS_NAME=teemops_main
TOPS_SQS_ARN=arn:aws:sqs:...
SCAN_QUEUE_CONNECTION=sqs-audit
SCAN_REGION_QUEUE_CONNECTION=sqs-audit-region
QUEUE_CONNECTION=database
```

---

## 7. How to run (current)

### Phase 1 only (no AWS messaging)

```bash
cp .env.docker.example .env
chmod +x docker/scripts/prepare-build.sh
./docker/scripts/prepare-build.sh
docker compose build
docker compose up -d
```

Open http://localhost:8080 — Laravel login when `FIREBASE_USER_AUTH=false`.

### Phase 1 + 2 (with AWS messaging)

```bash
# Set in .env:
# TOPS_DEPLOYMENT_REGION=us-east-1
# AWS credentials in ~/.aws

chmod +x install.sh
./install.sh
docker compose up -d --build
```

See also [docker-compose.README.md](../../docker-compose.README.md) at repo root.

---

## 8. Remaining gaps / follow-ups

- **Software updates & schema migrations** for self-hosted customers (process not fully designed in this session).
- **Phase 3–6** per roadmap above.
- **Re-run `./install.sh`** after template or bucket policy changes to refresh `generated/teemops.env` and re-upload child CFN template.
- **`infra/cloud-stack/deploy/INSTRUCTIONS.md`** still references legacy NZ region — update when touching manual deploy docs.
- **Existing stacks** using old bucket name `{env}-tops-deploy` may need manual cleanup on redeploy.

---

## 9. Reference: important paths

| Purpose | Path |
|---------|------|
| Compose stack | `/docker-compose.yml` |
| Install orchestration | `/install.sh`, `/docker-compose.install.yml` |
| App container | `/docker/app/` |
| Installer | `/docker/installer/` |
| Messaging SAM (docker) | `/infra/cloud-stack/core-docker/` |
| Full core (EC2 path) | `/infra/cloud-stack/core/` |
| SNS template | `/infra/cloud-stack/stackset/sns.topic.cfn.yaml` |
| Child account CFN | `/templates/iam.role.child.account.cfn.yaml` |
| Feature flag config | `/app/config/features.php` |
| API auth middleware | `/app/app/Http/Middleware/VerifyFirebaseToken.php` |
| AWS account init API | `/app/app/Http/Controllers/Api/AwsAccountsController.php` |
| Generated env (runtime) | `/generated/teemops.env` |

---

## 10. Related docs

- [docker-compose.README.md](../../docker-compose.README.md) — operational runbook
- [infra/cloud-stack/README.md](../../infra/cloud-stack/README.md) — SAM stacks
- [docs/laravel-app/ENV_SETUP.md](../laravel-app/ENV_SETUP.md) — Laravel env vars
- [docs/laravel-app/SQS_POLLING_SERVICE.md](../laravel-app/SQS_POLLING_SERVICE.md) — account linking queue
- [docs/features/features-spec.md](../features/features-spec.md) — AWS account onboarding spec
