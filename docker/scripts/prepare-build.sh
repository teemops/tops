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

# The backup container runs as root (MySQL's datadir is 0640 mysql:mysql and
# unreadable otherwise) and chowns finished backups back to this uid:gid, so
# ~/.tops/backups stays readable and deletable from the host shell without
# sudo. Detected here rather than hardcoded to 1000 because macOS, WSL and
# multi-user hosts all disagree about that.
if [[ -f "$ENV_FILE" ]]; then
  for pair in "TOPS_BACKUP_UID=$(id -u)" "TOPS_BACKUP_GID=$(id -g)" "TOPS_BACKUP_TZ=$(
        cat /etc/timezone 2>/dev/null \
        || (readlink -f /etc/localtime 2>/dev/null | sed 's|.*/zoneinfo/||') \
        || echo UTC)"; do
    key="${pair%%=*}"
    if grep -qE "^${key}=" "$ENV_FILE"; then
      sed -i "s|^${key}=.*|${pair}|" "$ENV_FILE"
    else
      echo "$pair" >>"$ENV_FILE"
    fi
  done
  echo "Set TOPS_BACKUP_UID/GID/TZ in $ENV_FILE for the backup scheduler"
fi

cd "$ROOT/app"

echo "Installing PHP dependencies..."
composer install --no-dev --no-interaction --no-scripts --prefer-dist --optimize-autoloader

echo "Installing Node dependencies and building frontend..."
npm ci
npm run build

echo "Build artifacts ready in app/vendor and app/public/build"
