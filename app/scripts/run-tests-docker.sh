#!/usr/bin/env bash
#
# Run the PHP test suite inside a Docker PHP image.
#
# The suite uses an in-memory SQLite database (see phpunit.xml), which needs the
# pdo_sqlite extension. Use this when your local PHP does not have it — the
# official php images do. Any arguments are passed through to `artisan test`:
#
#   ./scripts/run-tests-docker.sh
#   ./scripts/run-tests-docker.sh --filter=OrganizationPermissionTest
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
IMAGE="${TOPS_TEST_IMAGE:-php:8.3-cli}"

if [ ! -d "$APP_DIR/vendor" ]; then
    echo "vendor/ is missing. Run 'composer install' first." >&2
    exit 1
fi

if [ ! -f "$APP_DIR/.env" ]; then
    echo ".env is missing. Run 'cp .env.example .env && php artisan key:generate' first." >&2
    exit 1
fi

exec docker run --rm \
    -v "$APP_DIR":/app \
    -w /app \
    "$IMAGE" \
    php artisan test "$@"
