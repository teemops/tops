# Teemops Docker Compose (Phase 1–3)

There are two ways to run the stack (Laravel app, MySQL, Maildev, queue worker).
Pick by whether you intend to change the code.

## Running it — published images

Needs **Docker only**. No PHP, Composer, Node or npm.

```bash
./install.sh
```

Or without a clone at all:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/teemops/tops/master/install.sh)
```

It pulls `teem/tops` and `teem/tops-backup` from Docker Hub, writes a `.env` with
a generated `APP_KEY`, pins `TOPS_IMAGE_TAG` to the version it installed, and
starts the stack. Re-running it is safe — an existing `.env` and `APP_KEY` are
left alone.

Upgrading, and rolling back:

```bash
docker compose pull && docker compose up -d
```

To roll back, set `TOPS_IMAGE_TAG=v0.1.0` in `.env` and run `docker compose up -d`.
Migrations run automatically from the container entrypoint on every start.

`latest` tracks the `develop` branch, so it moves on every release. You are not
exposed to that by default — `install.sh` pins `TOPS_IMAGE_TAG` to the version it
installed, and you only move when you choose to pull.

## Building it — contributors

Needs Docker **plus** PHP 8.3, Composer, Node >=22.12 and npm on the host.

```bash
./install-build.sh
```

That runs `docker/scripts/prepare-build.sh` to produce `app/vendor` and
`app/public/build`, then builds the images from your working tree via
`docker-compose.build.yml` instead of pulling them.

The equivalent by hand:

```bash
./docker/scripts/prepare-build.sh
docker compose -f docker-compose.yml -f docker-compose.build.yml build
docker compose -f docker-compose.yml -f docker-compose.build.yml up -d
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
./install-messaging.sh
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

On EC2 without mounted credentials, set `TOPS_INSTALLER_NETWORK=host` in `.env` before `./install-messaging.sh`.

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
- If messaging hasn't been installed, "Add AWS Account" returns a clear message to run `./install-messaging.sh` first.
- If CloudFormation is slow or fails, the modal offers **Enter details manually** (paste the AWS account ID + IAM role ARN).

## Services

| Service | Purpose |
|---------|---------|
| `app` | nginx + PHP-FPM, runs migrations on start |
| `mysql` | Application database |
| `maildev` | Captures outbound mail in dev |
| `worker` | Database queue worker; after Phase 2 install, also SQS scan + account workers |
| `backup` | Database backup scheduler — daily full, hourly differential, 15-min binlog archive |
| `db-restore` | Restore worker; behind the `restore` profile, only run by `./backup.sh restore` |

## Database backups

The `backup` service starts with the stack and needs no setup. Three tiers:

| Tier | Schedule | What it captures |
|------|----------|------------------|
| Full | daily 17:00 local | Complete physical copy of the datadir |
| Differential | hourly at :10 | Pages changed since the latest full |
| Transactional | every 15 min | Binary logs — the transaction stream, for point-in-time recovery |

Backups land in `~/.tops/backups` on the host (`TOPS_BACKUP_DIR`), outside the
repo. Manage them with `./backup.sh`:

```bash
./backup.sh status
```

Verify recovery actually works — this runs all three restore scenarios against
a throwaway MySQL instance and never touches your development database:

```bash
./backup.sh test
```

Full reference, including the three restore procedures and the MySQL engine
settings they depend on: [docs/backups.md](docs/backups.md).

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
