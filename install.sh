#!/usr/bin/env bash
# Teemops Phase 2 installer — deploy AWS messaging and generate generated/teemops.env
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

if [[ -f .env ]]; then
  set -a
  # shellcheck disable=SC1091
  source .env
  set +a
elif [[ -f .env.docker.example ]]; then
  echo "No .env found. Copy .env.docker.example to .env first:" >&2
  echo "  cp .env.docker.example .env" >&2
  exit 1
fi

if [[ -z "${TOPS_DEPLOYMENT_REGION:-}" ]]; then
  echo ""
  read -rp "AWS region for messaging (TOPS_DEPLOYMENT_REGION): " TOPS_DEPLOYMENT_REGION
  if [[ -z "$TOPS_DEPLOYMENT_REGION" ]]; then
    echo "TOPS_DEPLOYMENT_REGION is required." >&2
    exit 1
  fi
  export TOPS_DEPLOYMENT_REGION
  if grep -q '^TOPS_DEPLOYMENT_REGION=' .env 2>/dev/null; then
    sed -i "s|^TOPS_DEPLOYMENT_REGION=.*|TOPS_DEPLOYMENT_REGION=${TOPS_DEPLOYMENT_REGION}|" .env
  else
    echo "TOPS_DEPLOYMENT_REGION=${TOPS_DEPLOYMENT_REGION}" >> .env
  fi
fi

export TOPS_ENVIRONMENT="${TOPS_ENVIRONMENT:-test}"
export TOPS_INSTALL_TARGET="${TOPS_INSTALL_TARGET:-docker}"

echo "Teemops installer (target=${TOPS_INSTALL_TARGET}, region=${TOPS_DEPLOYMENT_REGION}, env=${TOPS_ENVIRONMENT})"
echo ""

COMPOSE=(docker compose -f docker-compose.yml -f docker-compose.install.yml --profile install)

"${COMPOSE[@]}" build installer
"${COMPOSE[@]}" run --rm installer

echo ""
echo "Done. Start or restart the stack:"
echo "  docker compose up -d"
