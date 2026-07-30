# Running TOPS from source, without Docker

This is the non-Docker development path: Laravel and Vite running directly on your
machine, with each long-running process in its own terminal. It needs PHP 8.3,
Composer, Node >=22.12, npm and a MySQL you manage yourself.

Most people do not want this. To **run** TOPS, use `./install.sh` (Docker only). To
work on the code with Docker doing the heavy lifting, use `./install-build.sh`. Both
are described in [../docker-compose.README.md](../docker-compose.README.md).

Setup:

```bash
cd app && composer install && cp .env.example .env && php artisan key:generate
```

```bash
npm ci && php artisan migrate
```

This document was moved out of the root README on 2026-07-30 (roadmap N-3). It was
roughly 80 lines of the first thing a new operator read, describing a path almost
nobody takes.

## The processes

**Terminal 1 (Laravel)**:
```bash
cd app
php artisan serve
```

**Terminal 2 (Vite)**:
```bash
cd app
npm run dev
```

**Terminal 3 (Queue Worker)**:
```bash
cd app
php artisan queue:work
```
By default, scan jobs use the queue in `SCAN_QUEUE_CONNECTION` (see below). With `SCAN_QUEUE_CONNECTION=database`, the above command processes scan jobs. With SQS, use the workers in Terminal 7 instead.

**Terminal 4 (SQS Command)**:
```bash
cd app
php artisan aws:process-sqs
```

**Terminal 5 (SQS Polling Service)**:
```bash
cd app
php artisan aws:process-sqs --once
```

**Terminal 6 (Scheduler)**:
```bash
cd app
php artisan schedule:run
```

**Terminal 7 (Queue Workers for SQS)** (only if using SQS for scans):
```bash
cd app
php artisan queue:work sqs-audit --queue=teemops_audit
# In a second terminal, run the region worker:
php artisan queue:work sqs-audit-region --queue=teemops_audit_region
```

**Scan queue behaviour**
- **Local dev without SQS:** Set in `.env`:
  - `SCAN_QUEUE_CONNECTION=database`
  - `SCAN_REGION_QUEUE_CONNECTION=database`
  Then run **one** worker that processes both main and region jobs:
  ```bash
  php artisan queue:work database --queue=default,teemops_audit_region
  ```
  (Terminal 3 can use that command instead of plain `queue:work`.)
- **Production / SQS:** Set `SCAN_QUEUE_CONNECTION=sqs-audit` and `SCAN_REGION_QUEUE_CONNECTION=sqs-audit-region` (or leave unset). Create SQS queues `teemops_audit` and `teemops_audit_region`, set AWS credentials, and run both workers (Terminal 7). If the push to SQS fails, creating a scan returns 503.
- **Why “no status update” on region worker:** If you see “Scan with region-based service - waiting for region scans to complete” but the region worker never processes jobs, either (1) region jobs are going to SQS but the region worker is not running or is pointing at the wrong queue, or (2) use database for both (above) so one worker handles everything.
- **Scan stuck in “Running”:** If an EC2/S3 (region-based) scan stays “Running”, ensure the region worker is processing jobs (see above). To fix already-stuck scans, run: `php artisan scans:mark-stale-region-complete` (marks scans that have been running 60+ minutes as completed with partial results). Use `--dry-run` to list scans that would be updated.

Visit: http://localhost:8000


## Running specific tests

```bash
php artisan test --filter ProcessSqsMessagesTest
```

```bash
php artisan test --filter="ScanModelTest|ScansControllerTest|ProcessAuditScanJobTest"
```

Coverage needs the `pcov` or `xdebug` extension:

```bash
php artisan test --coverage
```

CI runs the same suite plus `scan:validate-rules` and a frontend build on every pull
request and push to `develop` — see
[`.github/workflows/tests.yml`](../.github/workflows/tests.yml). It is informational and
should not be added to the branch-protection required-checks list.
