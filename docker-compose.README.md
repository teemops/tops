# Teemops Docker Compose (Phase 1 + 2)

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
