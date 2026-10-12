# Hosting app.teemops.com

*Researched 2026-10-13 for **D-15** and **N-13** in [`roadmap.md`](../roadmap.md). Prices are
estimates, not from a pricing calculator. Anything not verified is marked.*

## The question

Can the hosted version run on **Cloudflare Containers**, with an AWS access key and secret
passed in as secrets, or does it need to be hosted on **AWS**?

## Verdict: AWS

Cloudflare Containers *can* run it — nothing in the docs rules it out — but three facts make
it the wrong home, and the third is decisive on its own.

1. **Ephemeral disk.** All container disk is ephemeral, and a container that sleeps restarts
   on a fresh disk ([FAQ](https://developers.cloudflare.com/containers/faq/)). MySQL has to
   live outside Cloudflare, and there is no documented fixed outbound IP to allowlist it to.
2. **No uptime guarantee, no autoscaling.** `sleepAfter` defaults to 10 minutes; platform
   events and host restarts can stop a container; autoscaling is "not today" (same FAQ). The
   queue workers would have to be kept alive by Durable Object code — process supervision we
   would be writing ourselves rather than a service with a restart policy.
3. **Credentials.** Secrets arrive in the container as environment variables at start
   ([docs](https://developers.cloudflare.com/containers/examples/env-vars-and-secrets/)).
   That means a **long-lived IAM user key** inside the app — and that key can
   `sts:AssumeRole` into **every customer's account**. With the role ARN and external id
   from our own database, one leak (SSRF, a debug page, a log line, a dependency) is the
   whole chain into every customer. Nothing rotates it. Cloudflare's credential injection
   adds secrets to outbound requests outside the sandbox, but AWS SigV4 signs in-process
   with the secret, so it does not appear to help. *(Not verified for AWS specifically.)*

On AWS, an **ECS task role** (or EC2 instance profile) gives the app temporary credentials
that rotate automatically and are picked up by the SDK's default credential chain with no
code change. CloudTrail attributes each session to the task. That is also what the product
tells customers: we assume a role with an external id and hold no static keys. A security
product cannot make that claim with an access key in an environment variable.

**What was confirmed in Cloudflare's favour:** Containers is GA (2026-04-13,
[changelog](https://developers.cloudflare.com/changelog/product/containers/)); outbound
internet is on by default and non-HTTP TCP appears to be allowed
([docs](https://developers.cloudflare.com/containers/platform-details/outbound-traffic/),
*not verified end to end*); inbound SNS is just HTTPS and would work. Hyperdrive from a
container is undocumented.

## Options compared

| | Cloudflare Containers | AWS (ECS Fargate) |
| --- | --- | --- |
| Web app | Worker + Durable Object-backed container | Fargate service behind an ALB |
| Queue workers | Kept awake by Durable Object code | Second service, same image, worker entrypoint — as `docker-compose.yml` today |
| MySQL | External (e.g. RDS over the public internet, TLS, no fixed IP to allowlist) | RDS MySQL in private subnets |
| Inbound SNS | HTTPS to the callback route | ALB, or SNS → SQS (already in the core stack) |
| AWS credentials | **Static key in a secret** | **Task role**, temporary and auto-rotated |
| Rough monthly cost, small | ~$30–60 + RDS ~$15–25 | ~$70–110 (2 tasks, ALB, small RDS, NAT unless public subnets) |

**Ruled out on AWS:** App Runner closed to new customers on 2026-04-30
([notice](https://docs.aws.amazon.com/apprunner/latest/dg/apprunner-availability-change.html)).
Lambda would mean re-plumbing the queue and the long-running scan work, which breaks "exactly
the same product as open source".

**A cheaper first step:** `infra/cloud-stack/app/template.yaml` already deploys EC2 + ALB,
and `core` already has an EC2 role. One EC2 instance with an instance profile, running the
published Docker Hub images against RDS, gets IAM-role credentials and an identical product
for roughly $40–60 a month. Move to Fargate when zero-downtime deploys or a second host are
actually needed. **N-13 should decide between these two in its Design phase.**

## On SAM, honestly

SAM adds nothing for containers: it has no ECS resource types, so the template is plain
CloudFormation (`AWS::ECS::*`) under the SAM transform, with `sam deploy` doing the
packaging. That is how the repo's `core`, `db` and `app` nested stacks already work, so
staying with it is consistent and there is no reason to adopt CDK. Just don't expect SAM to
make it "serverless".

## Deployment outline (N-13)

1. **Account and network** — a dedicated SaaS AWS account, separate from dev. VPC with
   public and private subnets (reuse the `core` VpcStack). ACM certificate for
   `app.teemops.com`.
2. **Data** — extend the `db` stack: RDS MySQL 8, automated backups, master password in
   Secrets Manager.
3. **Messaging** — reuse the SQS and SNS resources from `core` / `core-docker`
   (`teemops_main`, the audit queues, the account-linking topic).
4. **Compute** — ECS cluster (or one EC2 host, see above) running the same Docker Hub image
   as `app` behind the ALB and as `worker`. Task role scoped to `sts:AssumeRole` on
   `arn:aws:iam::*:role/<TOPS child role name>` plus its SQS queues; separate execution
   role. `APP_KEY` and the Stripe keys from Secrets Manager via the task definition's
   `secrets`. Drop `AWS_PROFILE` and the mounted `~/.aws` that `docker-compose.yml` uses.
5. **Migrations** — a one-off task running `php artisan migrate --force`, run by the
   pipeline before the service update.
6. **Pipeline** — GitHub Actions authenticating to AWS with **OIDC** (no stored keys). On a
   release tag, after the existing Docker Hub publish (D-8): `sam deploy` the stacks, run the
   migration task, roll both services to the new tag, smoke-test `/health` and an
   account-linking canary, roll back to the previous tag on failure.
7. **Operations** — CloudWatch logs and alarms (ALB 5xx, queue age, DLQ depth), backups
   verified by a restore, and a CloudTrail view of `AssumeRole` into customer accounts.

## Not verified

- Arbitrary outbound TCP from a Cloudflare container in practice.
- Hyperdrive or a fixed outbound IP for Containers.
- Whether credential injection could work for SigV4-signed requests.
- Exact Fargate, ALB and RDS prices.
- ECS Express Mode's feature set.
- Whether the app reads AWS credentials anywhere other than the SDK default chain — only
  `AWS_PROFILE` and the mounted credentials file in `docker-compose.yml` were seen.
