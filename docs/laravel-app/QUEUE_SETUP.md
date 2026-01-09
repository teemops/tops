# Queue Setup Guide

This guide explains how to set up and run Laravel queues for background scan processing.

## Overview

The application uses Laravel queues to process AWS security scans asynchronously. When a scan is created via the API, it's dispatched to a queue job that:

1. Assumes the IAM role in the customer's AWS account
2. Performs security scans across multiple AWS services (S3, IAM, EC2, RDS)
3. Stores findings in the database
4. Updates scan status

## Queue Configuration

The default queue connection is set to `database` in `.env`:

```env
QUEUE_CONNECTION=database
```

This uses the database to store queued jobs. For production, consider using Redis:

```env
QUEUE_CONNECTION=redis
```

## Database Tables

Queue tables are created automatically when you run migrations:

```bash
php artisan migrate
```

This creates:
- `jobs` - Stores queued jobs
- `failed_jobs` - Stores failed jobs for debugging

## Running Queue Workers

### Development

Run a single queue worker:

```bash
php artisan queue:work
```

This will process jobs one at a time. Press `Ctrl+C` to stop.

### Production

For production, you should run queue workers as background processes. Options:

#### Option 1: Supervisor (Recommended)

Install Supervisor:

```bash
sudo apt-get install supervisor
```

Create configuration file `/etc/supervisor/conf.d/teemops-worker.conf`:

```ini
[program:teemops-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/app/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/worker.log
stopwaitsecs=3600
```

Then:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start teemops-worker:*
```

#### Option 2: Systemd

Create `/etc/systemd/system/teemops-worker.service`:

```ini
[Unit]
Description=Teemops Queue Worker
After=network.target

[Service]
User=www-data
Group=www-data
Restart=always
ExecStart=/usr/bin/php /path/to/your/app/artisan queue:work database --sleep=3 --tries=3

[Install]
WantedBy=multi-user.target
```

Then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable teemops-worker
sudo systemctl start teemops-worker
```

#### Option 3: Laravel Horizon (For Redis)

If using Redis, consider Laravel Horizon for better queue management:

```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

Then run:

```bash
php artisan horizon
```

## Queue Commands

### Process Jobs
```bash
php artisan queue:work
```

### Process Jobs with Options
```bash
php artisan queue:work --tries=3 --timeout=300 --max-jobs=1000
```

Options:
- `--tries=3` - Number of times to retry failed jobs
- `--timeout=300` - Timeout in seconds for each job
- `--max-jobs=1000` - Restart worker after processing N jobs (prevents memory leaks)
- `--max-time=3600` - Restart worker after N seconds

### List Failed Jobs
```bash
php artisan queue:failed
```

### Retry Failed Jobs
```bash
php artisan queue:retry all
# Or retry specific job
php artisan queue:retry {job-id}
```

### Delete Failed Jobs
```bash
php artisan queue:flush
```

### Clear Queue
```bash
php artisan queue:clear
```

## Monitoring

### Check Queue Status

```bash
# Count pending jobs
php artisan queue:monitor database:default

# Check failed jobs
php artisan queue:failed
```

### Logs

Queue worker logs are written to `storage/logs/laravel.log`. For Supervisor/systemd, check their respective log files.

### Failed Jobs

Failed jobs are stored in the `failed_jobs` table. You can:

1. View them: `php artisan queue:failed`
2. Retry them: `php artisan queue:retry {id}`
3. Delete them: `php artisan queue:forget {id}`

## Scan Job Details

The `ProcessScanJob` class:

- **Tries:** 3 attempts before failing
- **Backoff:** 60 seconds between retries
- **Timeout:** Default Laravel timeout (usually 60 seconds, but scans may take longer)

### Job Flow

1. **Dispatch:** Scan created → Job dispatched to queue
2. **Processing:** Worker picks up job → Assumes AWS role → Runs scans
3. **Completion:** Findings stored → Scan marked complete
4. **Failure:** On error → Job retried up to 3 times → Marked failed if all retries exhausted

### Customizing Job Behavior

Edit `app/Jobs/ProcessScanJob.php` to customize:

- Retry attempts: `public int $tries = 3;`
- Retry delay: `public int $backoff = 60;`
- Timeout: Add `public int $timeout = 300;` for 5-minute timeout

## Troubleshooting

### Jobs Not Processing

1. **Check worker is running:**
   ```bash
   ps aux | grep "queue:work"
   ```

2. **Check queue connection:**
   ```bash
   php artisan tinker
   >>> config('queue.default')
   ```

3. **Check for errors in logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

### Jobs Failing

1. **Check failed jobs:**
   ```bash
   php artisan queue:failed
   ```

2. **Check error messages:**
   ```bash
   php artisan queue:failed
   # Note the ID, then:
   php artisan queue:failed {id}
   ```

3. **Common issues:**
   - AWS credentials not configured
   - IAM role ARN incorrect
   - Network connectivity issues
   - Database connection issues

### Memory Issues

If workers consume too much memory:

1. Use `--max-jobs` to restart workers periodically:
   ```bash
   php artisan queue:work --max-jobs=100
   ```

2. Use `--max-time` to restart after a time period:
   ```bash
   php artisan queue:work --max-time=3600
   ```

## Production Recommendations

1. **Use Redis** for better performance and reliability
2. **Run multiple workers** for parallel processing
3. **Monitor queue length** and scale workers as needed
4. **Set up alerts** for failed jobs
5. **Use Horizon** if using Redis for better management UI
6. **Configure proper timeouts** based on scan complexity
7. **Set up log rotation** to prevent disk space issues

