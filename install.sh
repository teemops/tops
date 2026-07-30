#!/usr/bin/env bash
# TOPS installer — runs the published Docker images. Builds nothing.
#
# The only host dependency is Docker. No PHP, no Composer, no Node, no npm.
#
# From a clone:
#
#   ./install.sh
#
# Or straight from the web:
#
#   bash <(curl -fsSL https://raw.githubusercontent.com/teemops/tops/develop/install.sh)
#
# Prefer to read it first? That is the better habit, and it is two commands:
#
#   curl -fsSL https://raw.githubusercontent.com/teemops/tops/develop/install.sh -o install.sh
#   less install.sh && bash install.sh
#
# Contributors building from source want ./install-build.sh instead.
# Connecting an AWS account is a separate, later step: ./install-messaging.sh.

set -euo pipefail

TOPS_REPO="${TOPS_REPO:-teemops/tops}"
TOPS_DIR="${TOPS_DIR:-tops}"
TOPS_VERSION="${TOPS_VERSION:-}"

# Where the release tarball is unpacked before being moved into place. Cleaned up
# by a single EXIT trap: a `trap ... RETURN` inside the download function is not
# scoped to it — it fires on every later function return too, by which point a
# `local` temp path is out of scope and `set -u` aborts a run that had already
# succeeded. That bug shipped in v0.1.0 and printed an error after a working
# install.
DOWNLOAD_DIR=""
cleanup() {
  [[ -n "${DOWNLOAD_DIR:-}" && -d "${DOWNLOAD_DIR:-}" ]] && rm -rf "$DOWNLOAD_DIR"
  return 0
}
trap cleanup EXIT

# `bash <(curl ...)` makes BASH_SOURCE a /dev/fd entry, so the script's own path
# tells us nothing about whether a checkout exists. Look for the files we
# actually need instead.
have_checkout() {
  [[ -f docker-compose.yml && -d docker/mysql/conf.d ]]
}

log()  { printf '%s\n' "$*"; }
step() { printf '\n==> %s\n' "$*"; }
die()  { printf 'Error: %s\n' "$*" >&2; exit 1; }

require() {
  command -v "$1" >/dev/null 2>&1 || die "$2"
}

preflight() {
  step "Checking prerequisites"

  require docker "Docker is not installed. Install it from https://docs.docker.com/get-docker/ and run this again."

  if ! docker info >/dev/null 2>&1; then
    cat >&2 <<'EOF'
Error: the Docker daemon is not reachable from this user.

Check that Docker is running and that your account can access the socket.
On Linux:

  sudo usermod -aG docker $USER

Then log out and back in (or run: newgrp docker).
EOF
    exit 1
  fi

  # Compose v2 is a docker subcommand; the old standalone docker-compose is not
  # supported here because the compose files use `env_file: path:/required:`.
  docker compose version >/dev/null 2>&1 \
    || die "Docker Compose v2 is required (the 'docker compose' subcommand). Update Docker Desktop, or install the compose plugin."

  log "Docker and Compose v2 are available."
}

resolve_version() {
  if [[ -n "$TOPS_VERSION" ]]; then
    log "Using pinned version: $TOPS_VERSION"
    return
  fi

  if [[ -f VERSION ]]; then
    TOPS_VERSION="$(tr -d '[:space:]' < VERSION)"
    log "Using version from VERSION file: $TOPS_VERSION"
    return
  fi

  step "Resolving the latest release"
  TOPS_VERSION="$(
    curl -fsSL "https://api.github.com/repos/${TOPS_REPO}/releases/latest" \
      | sed -n 's/.*"tag_name" *: *"v\{0,1\}\([^"]*\)".*/\1/p' \
      | head -n 1
  )" || true

  [[ -n "$TOPS_VERSION" ]] \
    || die "Could not determine the latest release of ${TOPS_REPO}. Set TOPS_VERSION=x.y.z and retry."

  log "Latest release: $TOPS_VERSION"
}

fetch_release() {
  step "Downloading TOPS ${TOPS_VERSION}"

  require curl "curl is required to download TOPS."
  require tar "tar is required to unpack the download."

  if [[ -d "$TOPS_DIR" ]]; then
    log "Directory '$TOPS_DIR' already exists — using it."
    cd "$TOPS_DIR"
    have_checkout || die "'$PWD' exists but does not look like a TOPS checkout. Move it aside, or set TOPS_DIR to somewhere else."
    return
  fi

  local url="https://github.com/${TOPS_REPO}/archive/refs/tags/v${TOPS_VERSION}.tar.gz"

  DOWNLOAD_DIR="$(mktemp -d)"

  curl -fsSL "$url" -o "$DOWNLOAD_DIR/tops.tar.gz" \
    || die "Download failed: $url"

  mkdir -p "$TOPS_DIR"
  # --strip-components drops the repo-name-and-tag wrapper directory GitHub adds.
  tar -xzf "$DOWNLOAD_DIR/tops.tar.gz" -C "$TOPS_DIR" --strip-components=1

  cd "$TOPS_DIR"
  log "Unpacked into $PWD"
}

# Writes to a temp file and moves it into place, so an interrupted run cannot
# leave a half-written .env that later steps would silently misread.
write_env() {
  step "Preparing configuration"

  if [[ -f .env ]]; then
    log ".env already exists — leaving it alone."
  else
    [[ -f .env.docker.example ]] || die ".env.docker.example is missing from this checkout."
    cp .env.docker.example .env
    log "Created .env from .env.docker.example"
  fi

  local tmp
  tmp="$(mktemp)"
  cp .env "$tmp"

  set_var() {
    local key="$1" value="$2"
    if grep -qE "^${key}=" "$tmp"; then
      sed -i.bak "s|^${key}=.*|${key}=${value}|" "$tmp" && rm -f "$tmp.bak"
    else
      printf '%s=%s\n' "$key" "$value" >> "$tmp"
    fi
  }

  # One stable APP_KEY, shared by the app and worker containers: the worker
  # encrypts IAM role ARNs that the app has to decrypt, so this must never
  # rotate once data exists.
  if ! grep -qE '^APP_KEY=base64:.+' "$tmp"; then
    set_var APP_KEY "base64:$(openssl rand -base64 32)"
    log "Generated APP_KEY"
  else
    log "APP_KEY already set — keeping it."
  fi

  # Finished backups are chowned to this uid:gid so ~/.tops/backups stays
  # readable from the host shell without sudo. Detected rather than hardcoded,
  # because macOS, WSL and multi-user hosts all disagree about 1000.
  set_var TOPS_BACKUP_UID "$(id -u)"
  set_var TOPS_BACKUP_GID "$(id -g)"
  set_var TOPS_BACKUP_TZ "$(
    cat /etc/timezone 2>/dev/null \
      || (readlink -f /etc/localtime 2>/dev/null | sed 's|.*/zoneinfo/||') \
      || echo UTC
  )"

  # Pin the images to the version we just installed, so a later `docker compose
  # up` cannot silently jump to a newer release.
  set_var TOPS_IMAGE_TAG "v${TOPS_VERSION}"

  mv "$tmp" .env
  log "Pinned images to v${TOPS_VERSION} (change TOPS_IMAGE_TAG in .env to move)"

  # The app and worker bind-mount this read-only. Docker would create it as
  # root if missing, which then confuses the AWS SDK inside the container.
  mkdir -p "${HOME}/.aws"
}

# The compose services use fixed container_name values, so a leftover container
# from an earlier install — or a second checkout — collides by name. Docker's
# own error for this is opaque, so say what it means and what to do.
check_name_conflicts() {
  local project existing name
  project="$(docker compose config --format json 2>/dev/null | sed -n 's/.*"name" *: *"\([^"]*\)".*/\1/p' | head -n 1)"

  for name in teemops-app teemops-worker teemops-mysql teemops-maildev teemops-backup; do
    existing="$(docker ps -a --filter "name=^/${name}$" --format '{{.Label "com.docker.compose.project"}}' 2>/dev/null || true)"
    [[ -n "$existing" && "$existing" != "$project" ]] || continue

    cat >&2 <<EOF

Error: a container named '${name}' already exists, from a different TOPS
install (compose project '${existing}', this one is '${project}').

Docker will not let two containers share a name. Either stop that install:

  docker stop ${name} && docker rm ${name}

or, if it is a TOPS instance you still want, run this installer from its
directory instead of here.
EOF
    exit 1
  done
}

start_stack() {
  check_name_conflicts

  step "Pulling images"
  docker compose pull

  step "Starting TOPS"
  docker compose up -d

  step "Waiting for the application to answer"
  local port
  port="$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d= -f2 || true)"
  port="${port:-8080}"

  local i
  for i in $(seq 1 60); do
    if curl -fsS "http://localhost:${port}/health" >/dev/null 2>&1; then
      log "Healthy."
      APP_PORT_RESOLVED="$port"
      return 0
    fi
    sleep 2
  done

  cat >&2 <<EOF

TOPS started but did not answer on http://localhost:${port}/health within two minutes.

Check the logs:

  docker compose logs app

EOF
  exit 1
}

main() {
  preflight

  if have_checkout; then
    log "Running inside an existing TOPS checkout."
    resolve_version
  else
    resolve_version
    fetch_release
  fi

  write_env
  start_stack

  cat <<EOF

TOPS ${TOPS_VERSION} is running.

  Open      http://localhost:${APP_PORT_RESOLVED}
  Mail UI   http://localhost:8090   (sign-up and reset emails land here)

Register the first account in the browser, then to scan a real AWS account:

  cd $(pwd)
  ./install-messaging.sh

That step deploys SQS, SNS and an S3 bucket into your own AWS account — read
docker-compose.README.md before running it, so you know what it creates and how
to remove it.

Useful commands:

  docker compose logs -f app     follow the application log
  docker compose down            stop TOPS (your data is kept)
  docker compose pull && docker compose up -d    upgrade in place

EOF
}

main "$@"
