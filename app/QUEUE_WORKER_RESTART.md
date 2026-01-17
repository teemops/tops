# Queue Worker Restart Guide

## Why Restart Queue Workers?

Laravel queue workers cache your application code when they start. This means:
- **Code changes won't be picked up** until you restart the worker
- **New classes/methods won't be available** until restart
- **Configuration changes** may not be applied until restart

## How to Restart Queue Workers

### If Running Manually (Development)

If you're running the queue worker manually:

```bash
# Stop the current worker (Ctrl+C)
# Then restart it:
php artisan queue:work sqs-audit --queue=teemops_audit
```

### If Using Supervisor

```bash
# Restart all workers
sudo supervisorctl restart teemops-worker:*

# Or restart specific worker
sudo supervisorctl restart teemops-worker:teemops-worker_00
```

### If Using Systemd

```bash
# Restart the service
sudo systemctl restart teemops-worker
```

### Quick Restart Command

For development, you can use:

```bash
# Kill existing workers and restart
pkill -f "queue:work" && php artisan queue:work sqs-audit --queue=teemops_audit
```

## When to Restart

You should restart queue workers after:
- ✅ Changing any PHP code (Jobs, Services, Models, etc.)
- ✅ Updating `tasks.json` files (they're loaded at runtime, but code changes need restart)
- ✅ Changing configuration that affects job processing
- ✅ Adding new job classes
- ✅ Modifying service classes (like RulesEngine, IamScanner, etc.)

## Verifying Restart Worked

After restarting, check the logs to see if your changes are being used:

```bash
tail -f storage/logs/laravel.log | grep -i "getUser\|params\|UserName"
```

You should see the new debug logs showing params being passed.

## Troubleshooting

If changes still don't appear after restart:

1. **Verify the worker actually restarted:**
   ```bash
   ps aux | grep "queue:work"
   ```
   Check the process start time - it should be recent.

2. **Clear Laravel cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

3. **Check for multiple workers:**
   ```bash
   ps aux | grep "queue:work"
   ```
   Make sure you restart ALL workers, not just one.

4. **Verify code changes are saved:**
   ```bash
   # Check file modification time
   ls -la app/app/Services/RulesEngine/RulesEngine.php
   ```
