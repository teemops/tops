# SQS Polling Service Setup

This guide explains how to set up and run the SQS polling service as a background process to handle AWS account registration messages from CloudFormation.

## Overview

The SQS polling service (`ProcessSqsMessages` command) continuously polls an SQS queue for messages sent by CloudFormation when users create or delete AWS account stacks. The service:

1. Polls the SQS queue for new messages
2. Parses CloudFormation custom resource requests (Create/Delete)
3. Updates AWS account records in the database
4. Sends responses back to CloudFormation via ResponseURL
5. Deletes processed messages from the queue

## Message Format

Messages in the SQS queue follow the format described in `references/samples/`:

- **SNS Notification Wrapper**: Messages are wrapped in an SNS notification
- **CloudFormation Request**: The actual request is in the `Message` field (JSON-encoded)
- **Request Types**: `Create` (when stack is created) or `Delete` (when stack is deleted)
- **Response Required**: Must send a response to the `ResponseURL` to acknowledge processing

## Configuration

Set the following environment variables in `.env`:

```env
TOPS_SQS_NAME=your-sqs-queue-name
TOPS_SQS_ARN=arn:aws:sqs:region:account-id:your-sqs-queue-name
AWS_DEFAULT_REGION=us-east-1
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
```

## Running the Service

### Development (Manual)

Run the command manually:

```bash
# Process messages once and exit
php artisan aws:process-sqs --once

# Run continuously (long-running process)
php artisan aws:process-sqs
```

### Production (Background Service)

For production, you should run the service as a background daemon. Options:

#### Option 1: Supervisor (Recommended)

Supervisor is a process control system that can keep the SQS polling service running and automatically restart it if it crashes.

**Install Supervisor:**

```bash
sudo apt-get install supervisor
```

**Create configuration file** `/etc/supervisor/conf.d/teemops-sqs-polling.conf`:

```ini
[program:teemops-sqs-polling]
process_name=%(program_name)s
command=php /path/to/your/app/artisan aws:process-sqs
directory=/path/to/your/app
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/path/to/your/app/storage/logs/sqs-polling.log
stopwaitsecs=3600
```

**Replace paths:**
- `/path/to/your/app` - Full path to your Laravel application
- `user=www-data` - User that runs your web server (adjust if different)

**Start the service:**

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start teemops-sqs-polling:*
```

**Check status:**

```bash
sudo supervisorctl status teemops-sqs-polling
```

**View logs:**

```bash
tail -f /path/to/your/app/storage/logs/sqs-polling.log
```

**Stop the service:**

```bash
sudo supervisorctl stop teemops-sqs-polling:*
```

#### Option 2: Systemd

Create a systemd service file `/etc/systemd/system/teemops-sqs-polling.service`:

```ini
[Unit]
Description=Teemops SQS Polling Service
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/path/to/your/app
ExecStart=/usr/bin/php /path/to/your/app/artisan aws:process-sqs
Restart=always
RestartSec=10
StandardOutput=append:/path/to/your/app/storage/logs/sqs-polling.log
StandardError=append:/path/to/your/app/storage/logs/sqs-polling-error.log

[Install]
WantedBy=multi-user.target
```

**Replace paths:**
- `/path/to/your/app` - Full path to your Laravel application
- `User=www-data` - User that runs your web server (adjust if different)

**Start the service:**

```bash
sudo systemctl daemon-reload
sudo systemctl enable teemops-sqs-polling
sudo systemctl start teemops-sqs-polling
```

**Check status:**

```bash
sudo systemctl status teemops-sqs-polling
```

**View logs:**

```bash
sudo journalctl -u teemops-sqs-polling -f
```

**Stop the service:**

```bash
sudo systemctl stop teemops-sqs-polling
```

#### Option 3: Laravel Scheduler (Alternative)

If you prefer to poll periodically instead of continuously, you can use Laravel's scheduler:

**Add to `app/Console/Kernel.php` (Laravel 10 and below) or `routes/console.php` (Laravel 11+):**

```php
// Laravel 11+
use Illuminate\Support\Facades\Schedule;

Schedule::command('aws:process-sqs --once')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
```

**Then run the scheduler:**

```bash
# Add to crontab
* * * * * cd /path-to-your-app && php artisan schedule:run >> /dev/null 2>&1
```

**Note:** This approach polls every minute instead of continuously. For real-time processing, use Supervisor or Systemd.

## Monitoring

### Check Service Status

**Supervisor:**
```bash
sudo supervisorctl status teemops-sqs-polling
```

**Systemd:**
```bash
sudo systemctl status teemops-sqs-polling
```

### View Logs

**Application logs:**
```bash
tail -f storage/logs/laravel.log
```

**Service-specific logs:**
```bash
# Supervisor
tail -f storage/logs/sqs-polling.log

# Systemd
sudo journalctl -u teemops-sqs-polling -f
```

### Test the Service

1. **Check configuration:**
   ```bash
   php artisan aws:process-sqs --once
   ```

2. **Monitor for messages:**
   The service will poll the queue and process any messages. Check logs to see activity.

3. **Test with a CloudFormation stack:**
   Create a CloudFormation stack using the provided template URL. The service should receive the message and update the database.

## Troubleshooting

### Service Not Starting

1. **Check configuration syntax:**
   ```bash
   # Supervisor
   sudo supervisorctl reread
   
   # Systemd
   sudo systemctl daemon-reload
   ```

2. **Check permissions:**
   Ensure the service user has read/write access to:
   - Application directory
   - Storage/logs directory
   - Environment file (.env)

3. **Check AWS credentials:**
   Verify `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` are set correctly.

### No Messages Being Processed

1. **Verify SQS queue name:**
   Check that `TOPS_SQS_NAME` matches the actual queue name in AWS.

2. **Check AWS permissions:**
   The IAM user/role needs:
   - `sqs:ReceiveMessage`
   - `sqs:DeleteMessage`
   - `sqs:GetQueueUrl`

3. **Check queue visibility:**
   Ensure messages are visible (not in flight timeout).

### CloudFormation Responses Failing

1. **Check ResponseURL:**
   The URL should be accessible from your server.

2. **Check network:**
   Ensure your server can make outbound HTTPS requests.

3. **Check logs:**
   Look for errors in `storage/logs/laravel.log` related to HTTP requests.

## Security Considerations

1. **IAM Permissions:**
   Use least-privilege IAM policies. The service only needs SQS read/delete permissions.

2. **Environment Variables:**
   Never commit `.env` file. Use secure secret management in production.

3. **Network Security:**
   Ensure the service can only access necessary AWS services (SQS, S3 for ResponseURL).

4. **Logging:**
   Be careful not to log sensitive information (AWS credentials, account IDs in plain text).

## Performance

- **Long Polling:** The service uses 20-second long polling to reduce API calls
- **Batch Processing:** Processes up to 10 messages per poll
- **Error Handling:** Failed messages are logged but don't block processing of other messages
- **Resource Usage:** The service is lightweight and should have minimal CPU/memory impact

## Related Documentation

- [Queue Setup Guide](./QUEUE_SETUP.md) - For Laravel queue workers
- [AWS Account Management](../features/features-spec.md) - Feature specification
- [Sample Messages](../../references/samples/README.md) - Message format examples
