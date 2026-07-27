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

if [ -n "$TOPS_SQS_ARN" ]; then
    echo "AWS messaging configured — starting SQS workers (supervisord)..."
    exec /usr/bin/supervisord -c /etc/supervisor/worker-supervisord.conf
fi

# No AWS messaging: everything (including scan + region jobs) runs on the
# database queue. Region jobs are pushed to the 'teemops_audit_region' queue and
# audit jobs to 'default', so this single worker must listen to all of them or
# region scans are enqueued but never consumed (scan stays "running" forever).
echo "Starting database queue worker..."
exec php artisan queue:work database \
    --queue=default,teemops_audit,teemops_audit_region \
    --sleep=3 --tries=3 --max-time=3600
