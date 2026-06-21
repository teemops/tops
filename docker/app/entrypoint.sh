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

sync_env_from_compose APP_URL
sync_env_from_compose FIREBASE_USER_AUTH

if [ -z "${APP_KEY:-}" ] || [ "${APP_KEY}" = "base64:" ]; then
    php artisan key:generate --force --no-interaction
fi

php artisan config:clear --no-interaction
php artisan migrate --force --no-interaction

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
