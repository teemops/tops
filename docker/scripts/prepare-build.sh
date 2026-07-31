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

# Database passwords, on the same rule as APP_KEY: generated here so no real
# value is ever committed, and stable once set. MySQL bakes MYSQL_ROOT_PASSWORD
# and MYSQL_PASSWORD into its datadir on first init and never reads them again,
# so rotating either later locks you out instead of changing anything.
if [[ -f "$ENV_FILE" ]]; then
  MYSQL_PASSWORD_VALUE=""
  for key in MYSQL_ROOT_PASSWORD MYSQL_PASSWORD TOPS_BACKUP_PASSWORD; do
    # `|| true` because grep exits 1 when the key is absent altogether, and
    # under `set -o pipefail` that would abort the build rather than generate
    # the missing password.
    value="$(grep -E "^${key}=" "$ENV_FILE" | head -n 1 | cut -d= -f2- || true)"
    if [[ -z "$value" ]]; then
      value="$(openssl rand -base64 32)"
      if grep -qE "^${key}=" "$ENV_FILE"; then
        sed -i "s|^${key}=.*|${key}=${value}|" "$ENV_FILE"
      else
        echo "${key}=${value}" >> "$ENV_FILE"
      fi
      echo "Generated $key in $ENV_FILE"
    fi
    if [[ "$key" == MYSQL_PASSWORD ]]; then
      MYSQL_PASSWORD_VALUE="$value"
    fi
  done

  # Laravel's name for the same account as MYSQL_PASSWORD, so it always follows.
  if grep -qE '^DB_PASSWORD=' "$ENV_FILE"; then
    sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${MYSQL_PASSWORD_VALUE}|" "$ENV_FILE"
  else
    echo "DB_PASSWORD=${MYSQL_PASSWORD_VALUE}" >> "$ENV_FILE"
  fi

  # install-build.sh creates .env with a plain `cp`, so its mode comes from the
  # umask — 0644 on a typical host. That was harmless when the file held only
  # example values; now it holds the real database passwords. install.sh's .env
  # is already 0600 (it writes through mktemp), so this just makes the
  # build-from-source path match.
  chmod 600 "$ENV_FILE"
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
