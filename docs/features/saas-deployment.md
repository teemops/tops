# N-13 · SaaS Deployment — design

*Infrastructure; no user story (see `CLAUDE.md`). Decisions recorded 2026-10-13. Research
behind them: [`saas-hosting.md`](saas-hosting.md). Roadmap entry: N-13 in
[`roadmap.md`](../roadmap.md).*

## Decisions

| | Decision | Why |
| --- | --- | --- |
| Compute | **ECS Fargate**: `app` and `worker` as two services from the published Docker Hub image | ECS's deployment circuit breaker gives automatic rollback; no OS to patch or SSH into |
| Account | **848310106659** (existing) | Owner's call. See *Known risk* below |
| Region | **ap-southeast-2 (Sydney)**, everything | Auckland has no SES; one region for the whole stack keeps it simple |
| Credentials | **ECS task role**, no static keys anywhere | The whole reason for AWS over Cloudflare Containers |
| Email | **SES in ap-southeast-2** via the task role | Laravel's `ses` mailer uses the SDK default chain when no key is set |
| IaC | SAM-packaged CloudFormation in **`infra/saas/`** | Consistent with the repo; SAM has no ECS resources, so it is plain `AWS::ECS::*` |
| CI/CD | GitHub Actions → AWS via **OIDC** | No stored AWS keys in GitHub |
| DNS | `app.teemops.com` stays on Cloudflare, CNAME to the ALB | Zone already lives there |

## What is reused, and what is not

`infra/cloud-stack` predates the Docker images (D-8) and was written for an earlier hosted
product. **Reused** as nested templates: `core/vpc.cfn.yaml`, `db/rds.cfn.yaml` (password in
Secrets Manager), `core-docker/sqs.cfn.yaml`, `stackset/sns.topic.cfn.yaml` — the last two
are what `install.sh` deploys for every self-hosted install, so the SaaS version is, quite
literally, one more TOPS install plus compute.

**Not reused**, and kept out of the SaaS path:

- `core/iam.ec2.root.role.cfn.yaml` — `sts:AssumeRole` on `*`, `cloudformation:*`,
  `sns:*`, `sqs:*`, `ssm:*` on every resource, an IAM user group (the static-key pattern), and
  an EventBridge bus policy with `Principal: "*"`.
- `app/ec2.alb.launch.cfn.yaml` and `infra/deploy/` — a single EC2 instance deployed by SSH
  and `git pull`, with a port-80 listener that serves plain HTTP rather than redirecting.

Their `samconfig.toml` files also hardcode another account's id, subnets and a certificate
ARN in a public repo. Cleaning those up is out of scope here; noted for X-10's neighbourhood.

## Shape

```
Cloudflare DNS  app.teemops.com ──CNAME──▶ ALB (443, ACM cert; 80 → 301 to 443)
                                              │
                         ┌────────────────────┴──────────┐
VPC (2 AZ, public       │ ECS service "app"  (1 task)   │  teem/tops:<tag>, nginx+php-fpm
 + private subnets,     │ ECS service "worker" (1 task) │  same image, worker entrypoint
 no NAT gateway)        └────────────────────┬──────────┘
                                              │ task role: sts:AssumeRole on the TOPS child
                                              │ role name only, its SQS queues, SES send
                         RDS MySQL 8 (private subnets, Secrets Manager password, backups)
                         SQS + SNS (the same stacks install.sh deploys)
```

- **Tasks in public subnets with public IPs, inbound only from the ALB's security group.**
  That avoids a ~$33/month NAT gateway; nothing can reach a task directly.
- **RDS in private subnets**, reachable only from the tasks' security group.
- **Sizing to start:** app 0.5 vCPU / 1 GB; worker 1 vCPU / 2 GB (five region workers at
  ~0.6–0.8 GB, per PERF-6); `db.t4g.micro`. ARM (`linux/arm64`) — the images are published
  for both architectures.

## Things the container needs that docker-compose gave it

- **`APP_KEY` must come from Secrets Manager.** `docker/app/entrypoint.sh` generates one when
  it is missing, which on Fargate would give every task a different key and break sessions.
- **Migrations run on container start, unchanged from self-hosted.** The app service runs
  one task, so a rollout starts exactly one new one and migrations never run concurrently.
  A failed migration exits the container, the circuit breaker fails the rollout, and the
  stack rolls back. *A separate migration task was designed and dropped as unneeded at
  one task; it becomes necessary the day `DesiredCount` goes above 1.*
- **No `AWS_PROFILE`, no mounted `~/.aws`.** The SDK's default chain picks up the task role.
- **Mail:** `MAIL_MAILER=ses`, `AWS_DEFAULT_REGION=ap-southeast-2`, no key or secret.
- **Stateless:** sessions, cache and queues are all `database`; nothing in `app/` writes to
  local storage except logs, which go to stderr → CloudWatch.

## Pipeline

On a published release tag (after `tag-release.yml` has pushed images, D-8):

`.github/workflows/deploy-saas.yml`, in the `saas-production` GitHub environment:

1. Assume `teemops-saas-deploy` via OIDC — trusted only for that environment of
   `teemops/tops`, so required reviewers on it gate production.
2. `sam deploy` with the new image tag. CloudFormation (acting as `teemops-saas-cfn-exec`)
   updates both services and **waits for them to settle**; the ECS circuit breaker fails a
   rollout whose task never passes `/up`, and the stack rolls back to the previous image.
3. Upload the child-account template from the same release.
4. Smoke test `https://app.teemops.com/up` through the load balancer.

A manual run with an older tag is the deliberate rollback.

## Setup that is the owner's to do

- **ACM certificate validation** — a CNAME in Cloudflare (the stack outputs it).
- **`app.teemops.com` CNAME** to the ALB, in Cloudflare.
- **SES production access** — a support request; the account is in the SES sandbox.
- **Deploy `infra/saas/bootstrap.yaml` once** as an administrator, and create the
  `saas-production` GitHub environment. Step by step in
  [`infra/saas/README.md`](../../infra/saas/README.md).

## Known risk: shared account

848310106659 also hosts the owner's development install (`teemops-core-docker`,
`teemops-messaging-test` in us-west-2). The customer-side role in
`templates/iam.role.child.account.cfn.yaml` trusts **the whole parent account**
(`Principal: AWS: <ParentAWSAccountId>`) plus the external id. In a shared account, any
principal allowed `sts:AssumeRole` — an admin user, the dev install running on local keys —
can assume a SaaS customer's role given that customer's external id, which lives in the
SaaS database.

**Mitigation, proposed as a follow-up rather than done here:** let the child template take
an optional parent *role* ARN and trust only that (the SaaS task role), keeping the account
principal as the self-hosted default. Revisit the account choice before the first paying
customer.

## Acceptance criteria

The N-13 checklist in the roadmap, restated against this design:

- [ ] `infra/saas/` deploys VPC, RDS, SQS, SNS, ALB, ECS cluster and both services from
      templates — nothing created by hand except the owner-only steps above — *written,
      cfn-lint and `sam validate` clean; not yet deployed*
- [ ] No static AWS keys in the deployment; the app reaches AWS through the task role
- [ ] HTTP redirects to HTTPS; tasks unreachable except through the ALB
- [ ] A release tag deploys end to end via GitHub Actions + OIDC, migrations included, and
      a failed rollout rolls back automatically — *built; needs the first real deploy*
- [ ] RDS backups on, and one restore actually performed
- [ ] Account linking verified with a real customer-side stack
- [ ] Alarms on ALB 5xx, queue age and DLQ depth — *defined, plus no-healthy-task*
- [ ] Self-hosted install unchanged: `install.sh` tests and the app suite still pass
