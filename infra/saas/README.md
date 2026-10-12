# infra/saas — app.teemops.com

The hosted version of TOPS (D-15): the same published images a self-hosted install
runs, on ECS Fargate in **ap-southeast-2**, account **848310106659**. Design and the
reasoning behind it: [`docs/features/saas-deployment.md`](../../docs/features/saas-deployment.md).

| File | What | Deployed by |
| --- | --- | --- |
| `bootstrap.yaml` | GitHub OIDC trust, the deploy and CloudFormation roles, TLS certificate, SES identity, `APP_KEY` and install-id secrets, artifacts bucket | An administrator, **once** |
| `template.yaml` | VPC, RDS, SQS/SNS, ALB, ECS cluster, `app` and `worker` services, alarms | `.github/workflows/deploy-saas.yml`, on every release |

`template.yaml` nests four templates from `../cloud-stack/` (VPC, RDS, SQS, SNS) — the
SQS and SNS ones are exactly what `install.sh` deploys for a self-hosted install.

## First-time setup

Everything below is a one-off. After it, a release deploys itself.

### 1. Bootstrap the account

As an administrator, from the repo root:

```bash
aws cloudformation deploy --region ap-southeast-2 --stack-name teemops-saas-bootstrap --template-file infra/saas/bootstrap.yaml --capabilities CAPABILITY_NAMED_IAM
```

The stack **waits on the certificate**. While it does, open its Events in the console
(or `aws acm describe-certificate`) and add the validation CNAME in Cloudflare as
**DNS only** (grey cloud). It completes a few minutes after the record resolves.

### 2. DNS for mail

The bootstrap's `DkimRecords` output lists three tokens. For each, add in Cloudflare
(DNS only):

```
<token>._domainkey.teemops.com  CNAME  <token>.dkim.amazonses.com
```

Then request **SES production access** for ap-southeast-2 (console → SES → Account
dashboard). Until it is granted, SES only delivers to verified addresses.

### 3. GitHub environment

In the repo: **Settings → Environments → New environment → `saas-production`**.

- Add **required reviewers** — every production deploy then waits for approval.
- Variables: `AWS_DEPLOY_ROLE_ARN` (bootstrap output `DeployRoleArn`) and
  `AWS_ACCOUNT_ID` (`848310106659`).

No secrets: the job authenticates with GitHub's OIDC token.

### 4. First deploy

**Actions → Deploy app.teemops.com → Run workflow**, from `develop`, with the release
tag to run (e.g. `v0.5.0`). The first run takes ~20 minutes, mostly RDS.

### 5. Point app.teemops.com at it

The stack's `LoadBalancerDnsName` output → in Cloudflare:

```
app  CNAME  <LoadBalancerDnsName>
```

Leave it **DNS only** for now: the load balancer holds the certificate and redirects
HTTP to HTTPS itself.

### 6. Alarms

Subscribe an address to the `AlarmTopicArn` output:

```bash
aws sns subscribe --region ap-southeast-2 --protocol email --topic-arn <AlarmTopicArn> --notification-endpoint help@teemops.com
```

## Day to day

- **Deploys** happen when a release is tagged. Approve the run in the
  `saas-production` environment.
- **Rollback** is automatic when a rollout fails (circuit breaker, then stack
  rollback). To go back deliberately, run the workflow by hand with the older tag.
- **Logs:** CloudWatch group `/teemops-saas/app`, streams prefixed `app/` and `worker/`.
- **A shell in a task:** not enabled. Turn on ECS Exec when it is actually needed.

## Things to know

- **The app migrates on start**, as self-hosted does. Safe only because the app
  service runs one task; raising `DesiredCount` means moving migrations into a
  one-off task first.
- **Images come from Docker Hub anonymously.** Pull rate limits are per IP; if a
  deploy ever fails on `toomanyrequests`, add an ECR pull-through cache.
- **The account is shared with development** (see *Known risk* in the design doc):
  customer roles trust the whole account, so keep admin access to it small.
- **`APP_KEY` and the install id are retained** if the bootstrap stack is deleted.
  Losing the install id breaks every onboarding link already issued.
