#!/usr/bin/env bash
# TOPS contributor installer — builds the images from your working tree.
#
# This is the path to use when you are changing the code. It is the same flow
# that existed before published images: prepare-build.sh produces app/vendor and
# app/public/build on the host, then Compose builds the images from them.
#
#   ./install-build.sh
#
# Host dependencies, on top of Docker: PHP 8.3, Composer, Node >=22.12 and npm.
# If you only want to *run* TOPS, use ./install.sh instead — it pulls published
# images and needs none of those.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

COMPOSE=(docker compose -f docker-compose.yml -f docker-compose.build.yml)

log()  { printf '%s\n' "$*"; }
step() { printf '\n==> %s\n' "$*"; }
die()  { printf 'Error: %s\n' "$*" >&2; exit 1; }

step "Checking prerequisites"

command -v docker >/dev/null 2>&1 || die "Docker is not installed."
docker info >/dev/null 2>&1 || die "The Docker daemon is not reachable. On Linux: sudo usermod -aG docker \$USER, then log out and back in."
docker compose version >/dev/null 2>&1 || die "Docker Compose v2 is required (the 'docker compose' subcommand)."

missing=()
command -v php >/dev/null 2>&1      || missing+=("php")
command -v composer >/dev/null 2>&1 || missing+=("composer")
command -v node >/dev/null 2>&1     || missing+=("node")
command -v npm >/dev/null 2>&1      || missing+=("npm")

if (( ${#missing[@]} > 0 )); then
  cat >&2 <<EOF
Error: building from source needs these on the host, and they are missing:

  ${missing[*]}

Install them, or use ./install.sh to run the published images instead — that
path needs only Docker.
EOF
  exit 1
fi

log "Docker, PHP, Composer and Node are available."

if [[ ! -f .env ]]; then
  step "Creating .env"
  cp .env.docker.example .env
  log "Created .env from .env.docker.example"
fi

# Generates APP_KEY and the backup uid/gid/tz into .env, then builds
# app/vendor and app/public/build, which docker/app/Dockerfile requires.
step "Building host artifacts"
./docker/scripts/prepare-build.sh

step "Building images"
"${COMPOSE[@]}" build

step "Starting TOPS"
"${COMPOSE[@]}" up -d

port="$(grep -E '^APP_PORT=' .env 2>/dev/null | cut -d= -f2 || true)"
port="${port:-8080}"

cat <<EOF

TOPS is running from your working tree.

  Open      http://localhost:${port}
  Mail UI   http://localhost:8090

After changing PHP or frontend code, rebuild and restart:

  ./install-build.sh

To scan a real AWS account, deploy the messaging stack:

  ./install.sh --aws-only

That step only talks to AWS — it will not pull or start published images over
the ones you just built.

EOF
