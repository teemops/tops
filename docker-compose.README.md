# Teemops Docker Compose (Phase 1–3)

Run the full stack (Laravel app, MySQL, Maildev, queue worker):

```bash
cp .env.docker.example .env
chmod +x docker/scripts/prepare-build.sh install.sh
./docker/scripts/prepare-build.sh
docker compose build
docker compose up -d
```

Open http://localhost:8080 — health checks:

```bash
curl -s http://localhost:8080/health
curl -s http://localhost:8080/up
```

Maildev UI: http://localhost:8090

## Phase 2: AWS messaging (SQS + SNS)

Deploy real AWS queues in a single region and wire them into the app:

1. Set `TOPS_DEPLOYMENT_REGION` in `.env` (e.g. `us-east-1`).
2. Configure AWS credentials (`~/.aws/credentials` is mounted into containers).
3. Run the installer:

```bash
./install.sh
docker compose up -d --build
```

This creates:

| AWS resource | Purpose |
|--------------|---------|
| SQS `teemops_main` | CloudFormation custom-resource callbacks (child account linking) |
| SQS `teemops_audit` / `teemops_audit_region` | Scan job queues |
| SNS `teemops-sns` | Publishes to `teemops_main` in your deployment region |
| S3 `{env}-{account-id}-tops-deploy` | Deployment artifact bucket |

Output is written to `generated/teemops.env` (gitignored). The app and worker load it automatically. After install, the worker runs database + SQS consumers via supervisord.

Logs: `generated/install.log`

On EC2 without mounted credentials, set `TOPS_INSTALLER_NETWORK=host` in `.env` before `./install.sh`.

## Phase 3: Add an AWS account (end to end)

Once messaging is installed, connecting a customer AWS account works without any manual refresh:

1. Ensure the worker is polling for account-linking callbacks:

   ```bash
   docker compose logs -f worker   # look for: Polling SQS queue: teemops_main
   ```

2. In the app, open **AWS Accounts → Add AWS Account** and click **Open AWS Console**. This creates a pending account and opens a CloudFormation quick-create link in your deployment region (the region and parent account are baked into the URL — nothing is hard-coded).
3. Complete the CloudFormation stack in your AWS Console. Its custom resource notifies the `teemops-sns` topic, which fans out to the `teemops_main` SQS queue.
4. The worker's `aws:process-sqs` command consumes the message, activates the account, and replies to CloudFormation (the stack reaches `CREATE_COMPLETE`).
5. The modal is polling `GET /api/aws-accounts/{id}` and flips to **Account connected** on its own, then closes and refreshes the list — no manual refresh needed.

**Fallbacks**
- If messaging hasn't been installed, "Add AWS Account" returns a clear message to run `./install.sh` first.
- If CloudFormation is slow or fails, the modal offers **Enter details manually** (paste the AWS account ID + IAM role ARN).

## Services

| Service | Purpose |
|---------|---------|
| `app` | nginx + PHP-FPM, runs migrations on start |
| `mysql` | Application database |
| `maildev` | Captures outbound mail in dev |
| `worker` | Database queue worker; after Phase 2 install, also SQS scan + account workers |

## Logs

```bash
docker compose logs -f app
docker compose ps
```

## Reset database

```bash
docker compose down -v
docker compose up -d
```

## Feature flags

| Variable | Default (Docker) | Description |
|----------|------------------|-------------|
| `FIREBASE_USER_AUTH` | `false` | When false, uses Laravel email/password login (no Firebase OAuth). Set `true` and rebuild to enable Firebase. |
