#!/usr/bin/env bash
# Prepare app/vendor and public/build on the host before `docker compose build`.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

# Ensure a stable, shared APP_KEY exists in the root .env. Both the app and worker
# containers read it (see docker-compose.yml), so it must be one value that never
# rotates — the worker encrypts IAM role ARNs that the app decrypts.
ENV_FILE="$ROOT/.env"
if [[ -f "$ENV_FILE" ]]; then
  if ! grep -qE '^APP_KEY=base64:.+' "$ENV_FILE"; then
    APP_KEY_VALUE="base64:$(openssl rand -base64 32)"
    if grep -qE '^APP_KEY=' "$ENV_FILE"; then
      sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY_VALUE}|" "$ENV_FILE"
    else
      echo "APP_KEY=${APP_KEY_VALUE}" >> "$ENV_FILE"
    fi
    echo "Generated APP_KEY in $ENV_FILE"
  else
    echo "APP_KEY already set in $ENV_FILE"
  fi
else
  echo "WARNING: $ENV_FILE not found — copy .env.docker.example to .env so a shared APP_KEY can be generated." >&2
fi

cd "$ROOT/app"

echo "Installing PHP dependencies..."
composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

echo "Installing Node dependencies and building frontend..."
npm ci --legacy-peer-deps
npm run build

echo "Build artifacts ready in app/vendor and app/public/build"
