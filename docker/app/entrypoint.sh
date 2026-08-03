#!/bin/sh
set -e

cd /var/www/html

echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
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
echo "MySQL is ready."

if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
    else
        echo "ERROR: No .env or .env.example found in /var/www/html" >&2
        exit 1
    fi
fi

# Keep container .env in sync with compose-provided values (image ships without app/.env)
sync_env_from_compose() {
    _key="$1"
    _val=$(printenv "$_key" || true)
    if [ -z "$_val" ]; then
        return
    fi
    if grep -q "^${_key}=" .env; then
        sed -i "s|^${_key}=.*|${_key}=${_val}|" .env
    else
        echo "${_key}=${_val}" >> .env
    fi
}

sync_env_from_compose APP_KEY
sync_env_from_compose APP_URL
sync_env_from_compose FIREBASE_USER_AUTH
sync_env_from_compose AWS_DEFAULT_REGION
sync_env_from_compose TOPS_DEPLOYMENT_REGION
sync_env_from_compose AWS_PARENT_ACCOUNT_ID
sync_env_from_compose TOPS_CFN_TEMPLATE_URL
sync_env_from_compose TOPS_SQS_NAME
sync_env_from_compose TOPS_SQS_ARN
# The web tier builds the onboarding quick-create URL, and the install id is one of
# its parameters. Without it here, php-fpm hands out links whose ping the SNS topic
# filters out — the child stack then hangs for an hour and rolls back, and nothing
# is logged on our side because the message never arrives (N-11).
sync_env_from_compose TOPS_INSTALL_ID
# Read by SnsSignatureVerifier, which fails closed when it cannot tell whose topic
# a message came from. The legacy HTTP callback route lives in this tier.
sync_env_from_compose TOPS_SNS_ARN
sync_env_from_compose TOPS_QUARANTINE_SQS_NAME
# Queue connections come from generated/teemops.env (env_file). Bake them into the
# container .env too so the web tier (php-fpm) resolves them the same as CLI,
# regardless of php-fpm's environment handling. Scans use the database queue;
# only account linking uses SQS.
sync_env_from_compose QUEUE_CONNECTION
sync_env_from_compose SCAN_QUEUE_CONNECTION
sync_env_from_compose SCAN_REGION_QUEUE_CONNECTION

if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:" ]; then
    php artisan key:generate --force --no-interaction
fi

php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
