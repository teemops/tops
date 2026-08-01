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

## Run against SQLite instead of MySQL

`.env.example` defaults to MySQL, but you can run the whole dev app against a local
SQLite file — no MySQL server to manage. The test suite already runs on in-memory
SQLite (`phpunit.xml`); this is the same, but for the running app.

1. **Install the `pdo_sqlite` extension** for your PHP. On Debian/Ubuntu with PHP 8.3:
   ```bash
   sudo apt-get install php8.3-sqlite3
   ```
   Verify it loaded:
   ```bash
   php -m | grep sqlite
   ```

2. **Create the database file:**
   ```bash
   cd app && touch database/database.sqlite
   ```

3. **Point `.env` at SQLite.** Set `DB_CONNECTION=sqlite` (and remove or blank the
   MySQL `DB_HOST`/`DB_DATABASE`/`DB_USERNAME` lines — they are ignored for sqlite).
   `DB_DATABASE` defaults to `database/database.sqlite`, so you can leave it unset. The
   `SESSION_DRIVER`, `QUEUE_CONNECTION` and `CACHE_STORE=database` defaults work fine on
   SQLite — their tables are created by the standard migrations.

4. **Migrate:**
   ```bash
   php artisan migrate
   ```

Then start the processes below as normal. Note the shipped Docker app image only
bundles `pdo_mysql`, so this host-PHP path is the supported way to run on SQLite.

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
  Then run a worker that processes both main and region jobs:
  ```bash
  php artisan queue:work database --queue=default,teemops_audit_region
  ```
  (Terminal 3 can use that command instead of plain `queue:work`.)

  **One worker will be slow.** A full scan fans out to roughly 153 region jobs — nine
  regional services across ~17 regions — and a single worker runs them one at a time.
  Run several to see realistic timings:
  ```bash
  for i in 1 2 3 4 5; do
    php artisan queue:work database --queue=default,teemops_audit_region --stop-when-empty &
  done; wait
  ```
  This is safe: the database queue driver uses `SELECT … FOR UPDATE SKIP LOCKED` on
  MySQL 8, so workers never take the same job, and scan completion is settled by a job
  batch rather than by workers racing a counter.
- **Production / SQS:** Set `SCAN_QUEUE_CONNECTION=sqs-audit` and `SCAN_REGION_QUEUE_CONNECTION=sqs-audit-region` (or leave unset). Create SQS queues `teemops_audit` and `teemops_audit_region`, set AWS credentials, and run both workers (Terminal 7). If the push to SQS fails, creating a scan returns 503.
- **Why “no status update” on region worker:** If you see “Scan with region-based service - waiting for region scans to complete” but the region worker never processes jobs, either (1) region jobs are going to SQS but the region worker is not running or is pointing at the wrong queue, or (2) use database for both (above) so one worker handles everything.
- **Worker concurrency in Docker:** the worker container runs two supervisord pools — one
  for the orchestrator (`default`, `teemops_audit`) and one for region jobs
  (`teemops_audit_region`), sized by `TOPS_WORKER_PROCESSES` (default **5**). They are split
  so a long orchestrator job cannot block region jobs behind it. Each process holds the AWS
  SDK (~60–120 MB) and one MySQL connection, so 5 costs roughly 0.6–0.8 GB — **worth
  treating as a minimum-spec note for self-hosters.** Raising it also raises AWS API
  throttling risk against a single account.
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
