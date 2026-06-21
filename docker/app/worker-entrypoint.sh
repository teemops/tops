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

echo "Starting database queue worker..."
exec php artisan queue:work database --sleep=3 --tries=3 --max-time=3600
