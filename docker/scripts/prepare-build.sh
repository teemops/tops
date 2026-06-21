#!/usr/bin/env bash
# Prepare app/vendor and public/build on the host before `docker compose build`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT/app"

echo "Installing PHP dependencies..."
composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

echo "Installing Node dependencies and building frontend..."
npm ci --legacy-peer-deps
npm run build

echo "Build artifacts ready in app/vendor and app/public/build"
