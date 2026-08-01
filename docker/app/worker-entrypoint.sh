#!/bin/sh
set -e

cd /var/www/html

echo "Worker waiting for MySQL..."
until php -r "
    try {
        new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        exit(0);
    } catch (Throwable \$e) {
        exit(1);
    }
" 2>/dev/null; do
    sleep 2
done

# supervisord expands these with %(ENV_...)s and refuses to start if either is unset,
# so they are defaulted here rather than relied on from the compose file.
#
# Five region workers is the default from #69: enough to turn a ~10 minute serial scan
# into something a person will wait for, while staying inside the memory and AWS
# rate-limit budget a small self-hosted box has. Each process holds the AWS SDK
# (~60-120 MB) and a MySQL connection.
export TOPS_WORKER_PROCESSES="${TOPS_WORKER_PROCESSES:-5}"

# Account linking is the only thing that needs SQS. Without it the worker would
# restart-loop and bury every other log line.
if [ -n "$TOPS_SQS_ARN" ]; then
    export TOPS_ACCOUNT_QUEUE_AUTOSTART=true
    echo "AWS messaging configured — account-linking worker enabled."
else
    export TOPS_ACCOUNT_QUEUE_AUTOSTART=false
    echo "No AWS messaging — account-linking worker disabled; scans run on the database queue."
fi

echo "Starting workers (supervisord): ${TOPS_WORKER_PROCESSES} region worker(s)..."
exec /usr/bin/supervisord -c /etc/supervisor/worker-supervisord.conf
