#!/usr/bin/env bash
# TOPS installer — the only install script you need.
#
# It runs the published Docker images (it builds nothing), then offers to
# connect your AWS account. The only host dependency is Docker; the AWS step
# additionally wants the AWS CLI, so it can tell you which account you are
# about to deploy into.
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
# Options:
#
#   --aws        set up AWS messaging without asking first
#   --no-aws     install TOPS only, and do not offer the AWS step
#   --aws-only   skip the install and run just the AWS step (re-runnable)
#   --help       print this and exit
#
# Contributors building from source want ./install-build.sh instead. It builds
# the images from your working tree; come back to `./install.sh --aws-only`
# when you want to connect an account.

set -euo pipefail

TOPS_REPO="${TOPS_REPO:-teemops/tops}"
TOPS_DIR="${TOPS_DIR:-tops}"
TOPS_VERSION="${TOPS_VERSION:-}"

# ask | yes | no — what to do about the AWS step. Overridden by the flags above.
AWS_MODE="ask"
INSTALL_APP=1

# --- Colour ------------------------------------------------------------------
# Same palette as the original TeemOps installer, so the two look like the same
# product. Suppressed when stdout is not a terminal (piped into a log or a file)
# and when NO_COLOR is set, per https://no-color.org.
COLORS_RED=$'\033[0;31m'
COLORS_YELLOW=$'\033[1;33m'
COLORS_GREEN=$'\033[0;32m'
COLORS_BLUE=$'\033[0;34m'
COLORS_CYAN=$'\033[0;36m'
COLORS_BOLD=$'\033[1m'
COLORS_DIM=$'\033[2m'
COLORS_NC=$'\033[0m'

if [[ ! -t 1 || -n "${NO_COLOR:-}" ]]; then
  COLORS_RED= COLORS_YELLOW= COLORS_GREEN= COLORS_BLUE= COLORS_CYAN=
  COLORS_BOLD= COLORS_DIM= COLORS_NC=
fi

# The banner is box-drawing characters, which turn into mojibake on a non-UTF-8
# terminal. A garbled logo is a worse first impression than no logo.
banner() {
  if [[ "${LC_ALL:-${LC_CTYPE:-${LANG:-}}}" == *[Uu][Tt][Ff]* ]]; then
    printf '%s' "$COLORS_CYAN"
    cat <<'ART'

  ████████╗ ██████╗ ██████╗ ███████╗
  ╚══██╔══╝██╔═══██╗██╔══██╗██╔════╝
     ██║   ██║   ██║██████╔╝███████╗
     ██║   ██║   ██║██╔═══╝ ╚════██║
     ██║   ╚██████╔╝██║     ███████║
     ╚═╝    ╚═════╝ ╚═╝     ╚══════╝
ART
    printf '%s' "$COLORS_NC"
  else
    printf '\n%s  T O P S%s\n' "$COLORS_CYAN$COLORS_BOLD" "$COLORS_NC"
  fi

  printf '%s  Open-source AWS security scanning · Apache-2.0 · no limits, no paid tier%s\n\n' \
    "$COLORS_DIM" "$COLORS_NC"
}

log()   { printf '%s\n' "$*"; }
step()  { printf '\n%s==> %s%s\n' "$COLORS_BLUE$COLORS_BOLD" "$*" "$COLORS_NC"; }
ok()    { printf '%s✓%s %s\n' "$COLORS_GREEN" "$COLORS_NC" "$*"; }
note()  { printf '%s%s%s\n' "$COLORS_DIM" "$*" "$COLORS_NC"; }
warn()  { printf '%s! %s%s\n' "$COLORS_YELLOW" "$*" "$COLORS_NC" >&2; }
die()   { printf '%sError: %s%s\n' "$COLORS_RED" "$*" "$COLORS_NC" >&2; exit 1; }

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

require() {
  command -v "$1" >/dev/null 2>&1 || die "$2"
}

# Asks a yes/no question. Answers "no" without asking when there is no terminal
# to ask on — piping this script into bash must never hang waiting on a prompt.
confirm() {
  local reply=""
  [[ -t 0 ]] || return 1
  read -rp "${COLORS_BOLD}$1 [y/N] ${COLORS_NC}" reply || true
  [[ "$reply" =~ ^[Yy]([Ee][Ss])?$ ]]
}

# --- .env helpers ------------------------------------------------------------
# First match wins, the same way Compose reads the file.
env_get() { grep -E "^${2}=" "$1" 2>/dev/null | head -n 1 | cut -d= -f2- || true; }

env_set() {
  local file="$1" key="$2" value="$3"
  if grep -qE "^${key}=" "$file"; then
    sed -i.bak "s|^${key}=.*|${key}=${value}|" "$file" && rm -f "$file.bak"
  else
    printf '%s=%s\n' "$key" "$value" >> "$file"
  fi
}

usage() {
  cat <<EOF
TOPS installer — runs the published Docker images, then offers to connect
your AWS account.

  ./install.sh              install TOPS, then ask about the AWS step
  ./install.sh --aws        install TOPS and set up AWS without asking
  ./install.sh --no-aws     install TOPS only
  ./install.sh --aws-only   run just the AWS step, in an existing install
  ./install.sh --help       this

Environment: TOPS_VERSION pins a release, TOPS_DIR chooses the install
directory, NO_COLOR turns off colour.

Building from your working tree instead? Use ./install-build.sh.
EOF
}

parse_args() {
  while (( $# > 0 )); do
    case "$1" in
      --aws)      AWS_MODE="yes" ;;
      --no-aws)   AWS_MODE="no" ;;
      --aws-only) AWS_MODE="yes"; INSTALL_APP=0 ;;
      -h|--help)  usage; exit 0 ;;
      *)          die "Unknown option: $1 (try --help)" ;;
    esac
    shift
  done

  # --aws-only --no-aws asks for nothing at all. Say so rather than exiting 0
  # having done no work, which reads like a success.
  if (( ! INSTALL_APP )) && [[ "$AWS_MODE" == "no" ]]; then
    die "--aws-only and --no-aws contradict each other — there would be nothing to do."
  fi
}

# --- Phase 1: run TOPS -------------------------------------------------------

preflight() {
  step "Checking prerequisites"

  require docker "Docker is not installed. Install it from https://docs.docker.com/get-docker/ and run this again."

  if ! docker info >/dev/null 2>&1; then
    cat >&2 <<EOF
${COLORS_RED}Error: the Docker daemon is not reachable from this user.${COLORS_NC}

Check that Docker is running and that your account can access the socket.
On Linux:

  sudo usermod -aG docker \$USER

Then log out and back in (or run: newgrp docker).
EOF
    exit 1
  fi

  # Compose v2 is a docker subcommand; the old standalone docker-compose is not
  # supported here because the compose files use `env_file: path:/required:`.
  docker compose version >/dev/null 2>&1 \
    || die "Docker Compose v2 is required (the 'docker compose' subcommand). Update Docker Desktop, or install the compose plugin."

  ok "Docker and Compose v2 are available."
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
  ok "Unpacked into $PWD"
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

  # One stable APP_KEY, shared by the app and worker containers: the worker
  # encrypts IAM role ARNs that the app has to decrypt, so this must never
  # rotate once data exists.
  if ! grep -qE '^APP_KEY=base64:.+' "$tmp"; then
    env_set "$tmp" APP_KEY "base64:$(openssl rand -base64 32)"
    log "Generated APP_KEY"
  else
    log "APP_KEY already set — keeping it."
  fi

  # Database passwords, same rule as APP_KEY: generated here, never shipped in
  # the repo, and stable once set. MySQL writes MYSQL_ROOT_PASSWORD and
  # MYSQL_PASSWORD into its datadir the first time it initialises and never
  # reads them again, so rotating either one later locks you out of your own
  # database rather than changing anything.
  local mysql_password="" key existing
  for key in MYSQL_ROOT_PASSWORD MYSQL_PASSWORD TOPS_BACKUP_PASSWORD; do
    # env_get swallows grep's exit 1 when the key is absent altogether, which
    # under `set -o pipefail` would otherwise abort the install rather than
    # generate the missing password — the whole point of this loop.
    existing="$(env_get "$tmp" "$key")"
    if [[ -z "$existing" ]]; then
      existing="$(openssl rand -base64 32)"
      env_set "$tmp" "$key" "$existing"
      log "Generated $key"
    else
      log "$key already set — keeping it."
    fi
    if [[ "$key" == MYSQL_PASSWORD ]]; then
      mysql_password="$existing"
    fi
  done

  # DB_PASSWORD is the same account as MYSQL_PASSWORD — Laravel's name for it —
  # so it always follows, whether the password was just generated or already
  # there from an earlier run.
  env_set "$tmp" DB_PASSWORD "$mysql_password"

  # Finished backups are chowned to this uid:gid so ~/.tops/backups stays
  # readable from the host shell without sudo. Detected rather than hardcoded,
  # because macOS, WSL and multi-user hosts all disagree about 1000.
  env_set "$tmp" TOPS_BACKUP_UID "$(id -u)"
  env_set "$tmp" TOPS_BACKUP_GID "$(id -g)"
  env_set "$tmp" TOPS_BACKUP_TZ "$(
    cat /etc/timezone 2>/dev/null \
      || (readlink -f /etc/localtime 2>/dev/null | sed 's|.*/zoneinfo/||') \
      || echo UTC
  )"

  # Pin the images to the version we just installed, so a later `docker compose
  # up` cannot silently jump to a newer release.
  env_set "$tmp" TOPS_IMAGE_TAG "v${TOPS_VERSION}"

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

${COLORS_RED}Error: a container named '${name}' already exists, from a different TOPS
install (compose project '${existing}', this one is '${project}').${COLORS_NC}

Docker will not let two containers share a name. Either stop that install:

  docker stop ${name} && docker rm ${name}

or, if it is a TOPS instance you still want, run this installer from its
directory instead of here.
EOF
    exit 1
  done
}

app_port() {
  local port
  port="$(env_get .env APP_PORT)"
  printf '%s' "${port:-8080}"
}

start_stack() {
  check_name_conflicts

  step "Pulling images"
  docker compose pull

  step "Starting TOPS"
  docker compose up -d

  step "Waiting for the application to answer"
  local port i
  port="$(app_port)"

  for i in $(seq 1 60); do
    if curl -fsS "http://localhost:${port}/health" >/dev/null 2>&1; then
      ok "Healthy."
      return 0
    fi
    sleep 2
  done

  cat >&2 <<EOF

${COLORS_RED}TOPS started but did not answer on http://localhost:${port}/health within two minutes.${COLORS_NC}

Check the logs:

  docker compose logs app

EOF
  exit 1
}

# --- Phase 2: connect an AWS account -----------------------------------------

aws_intro() {
  cat <<EOF

${COLORS_YELLOW}${COLORS_BOLD}Connecting an AWS account${COLORS_NC}

To scan a real AWS account, TOPS needs a few messaging resources in an account
you own. This step deploys them with CloudFormation, into one region:

  ${COLORS_CYAN}CloudFormation${COLORS_NC}  2 stacks — teemops-core-docker and teemops-messaging
  ${COLORS_CYAN}SQS${COLORS_NC}             3 queues plus a dead-letter queue
  ${COLORS_CYAN}SNS${COLORS_NC}             1 topic, which notifies TOPS when an account is linked
  ${COLORS_CYAN}S3${COLORS_NC}              1 bucket, for deployment artefacts and the child-account template

These are pay-per-use and idle when you are not scanning, so the running cost is
cents per month at typical volumes. docker-compose.README.md lists exactly what
is created and how to remove it.

${COLORS_DIM}You can skip this. TOPS runs fine without it — you just cannot scan yet, and
you can come back later with: ./install.sh --aws-only${COLORS_NC}

EOF
}

# Confirms which account we are about to deploy into, and proves the CLI can
# talk to AWS at all. Getting this wrong means CloudFormation stacks in the
# wrong account, which is tedious to unpick — so it is worth showing the caller
# identity rather than assuming it.
AWS_ACCOUNT_ID=""
check_aws_identity() {
  if ! command -v aws >/dev/null 2>&1; then
    warn "The AWS CLI is not installed, so the account you are deploying into cannot be checked."
    note "  Install it: https://docs.aws.amazon.com/cli/latest/userguide/getting-started-install.html"
    return 1
  fi

  # One call, two fields, tab separated — cheaper than asking twice, and the ARN
  # is what tells you whether you are an assumed role or a long-lived user.
  local identity
  if ! identity="$(aws sts get-caller-identity --query '[Account,Arn]' --output text 2>/dev/null)"; then
    printf '%sFailed to get AWS account ID. Please check your AWS CLI configuration.%s\n' \
      "$COLORS_RED" "$COLORS_NC" >&2
    note "  Run 'aws configure', or set AWS_PROFILE / AWS_ACCESS_KEY_ID, then try again."
    return 1
  fi

  AWS_ACCOUNT_ID="$(printf '%s' "$identity" | cut -f1)"
  ok "AWS account ${COLORS_BOLD}${AWS_ACCOUNT_ID}${COLORS_NC} — $(printf '%s' "$identity" | cut -f2)"
}

# Region order of preference: what is already in .env (so a re-run is a no-op),
# then the environment, then the CLI's own configured region, then whatever the
# user types. Sets AWS_REGION_RESOLVED rather than echoing, so the prompt cannot
# end up inside the value.
AWS_REGION_RESOLVED=""
resolve_aws_region() {
  local region reply=""
  region="$(env_get .env TOPS_DEPLOYMENT_REGION)"
  region="${region:-${AWS_DEFAULT_REGION:-}}"

  if [[ -z "$region" ]] && command -v aws >/dev/null 2>&1; then
    region="$(aws configure get region 2>/dev/null || true)"
  fi

  if [[ -t 0 ]]; then
    read -rp "${COLORS_BOLD}AWS region to deploy into${COLORS_NC} [${region:-none set}]: " reply || true
    region="${reply:-$region}"
  fi

  AWS_REGION_RESOLVED="$region"
  [[ -n "$region" ]] || return 1

  # This is the one value a user types that then gets written into .env and
  # substituted into a sed replacement. A stray `&` or `|` would either corrupt
  # the file or abort the install with a sed error nobody can act on, so reject
  # anything that is not shaped like a region before it gets that far.
  if [[ ! "$region" =~ ^[a-z0-9-]+$ ]]; then
    warn "'$region' is not a valid AWS region name (expected something like us-east-1)."
    return 1
  fi
}

# Shape is not usability: 'ap-southeast-6' looks like a region and is one, but an
# account that has not opted into it gets InvalidClientTokenId from every call
# there — which reads as a credentials problem and is not one. Ask now, while the
# user is still at the prompt, rather than two minutes later inside the installer
# container. check_aws_identity has already passed by this point, so a failure
# here is about this region specifically. No CLI means no opinion.
check_region_usable() {
  command -v aws >/dev/null 2>&1 || return 0
  aws sts get-caller-identity --region "$1" >/dev/null 2>&1
}

setup_aws() {
  local region tmp
  local -a compose

  have_checkout || die "The AWS step must run from a TOPS directory (the one install.sh created)."
  [[ -f .env ]] || die "No .env here. Run ./install.sh first."

  aws_intro

  if [[ "$AWS_MODE" == "ask" ]] && ! confirm "Set up AWS messaging now?"; then
    return 1
  fi

  step "Checking your AWS credentials"
  if ! check_aws_identity; then
    if [[ "$AWS_MODE" == "yes" ]]; then
      die "AWS credentials are not usable. Fix the CLI configuration and re-run: ./install.sh --aws-only"
    fi
    warn "Skipping the AWS step."
    return 1
  fi

  resolve_aws_region \
    || die "No AWS region set. Run 'aws configure', or set TOPS_DEPLOYMENT_REGION in .env."
  region="$AWS_REGION_RESOLVED"

  check_region_usable "$region" || die "AWS rejected your credentials in ${region}, though they work in general.
Opt-in regions (ap-southeast-3 and up, ap-east-*, me-*, af-*, il-*, eu-south-*)
have to be enabled for the account first, under Account → AWS Regions in the
console. Enable ${region} there, or re-run and choose a region you already use."

  if [[ "$AWS_MODE" == "ask" ]] \
    && ! confirm "Deploy into account ${AWS_ACCOUNT_ID}, region ${region}?"; then
    warn "Skipping the AWS step."
    return 1
  fi

  tmp="$(mktemp)"
  cp .env "$tmp"
  env_set "$tmp" TOPS_DEPLOYMENT_REGION "$region"
  mv "$tmp" .env

  compose=(docker compose -f docker-compose.yml -f docker-compose.install.yml --profile install)

  step "Deploying AWS messaging into ${AWS_ACCOUNT_ID} (${region})"
  # Published image first, so this path stays true to "install.sh builds
  # nothing". A working tree ahead of the last release has no image to pull, so
  # fall back to building the installer locally — it needs no PHP or Node.
  if ! "${compose[@]}" pull installer >/dev/null 2>&1; then
    note "No published installer image for this version — building it locally."
    "${compose[@]}" build installer \
      || die "Could not build the installer image. The output above says why."
  fi

  # Every failure below has to be checked by hand, because `set -e` is not in
  # force here: main calls this function as an `if` condition, and bash disables
  # errexit for the whole body of a function invoked that way. Without these
  # checks a failed deploy fell through to the `ok` at the end of this function
  # and was reported as "AWS messaging is deployed" — which is how issue #56
  # reached the UI as a missing AWS_PARENT_ACCOUNT_ID instead of as a failed
  # install.
  "${compose[@]}" run --rm installer \
    || die "The AWS installer failed — see generated/install.log for what AWS said.
Nothing was written to generated/teemops.env, so TOPS still has no messaging
configuration. Fix the cause and re-run: ./install.sh --aws-only"

  [[ -s generated/teemops.env ]] \
    || die "The AWS installer exited cleanly but wrote no generated/teemops.env.
TOPS has no messaging configuration to load. See generated/install.log."

  if (( INSTALL_APP )); then
    step "Restarting TOPS with the new configuration"
    docker compose up -d \
      || die "AWS messaging deployed, but TOPS would not restart. Run: docker compose up -d"
  fi

  ok "AWS messaging is deployed. Details are in generated/teemops.env, log in generated/install.log."
}

# --- Output ------------------------------------------------------------------

summary() {
  local aws_done="$1" port
  port="$(app_port)"

  printf '\n%s%sTOPS %s is running.%s\n\n' "$COLORS_GREEN" "$COLORS_BOLD" "$TOPS_VERSION" "$COLORS_NC"
  printf '  %sOpen%s      http://localhost:%s\n' "$COLORS_BOLD" "$COLORS_NC" "$port"
  printf '  %sMail UI%s   http://localhost:8090   %s(sign-up and reset emails land here)%s\n' \
    "$COLORS_BOLD" "$COLORS_NC" "$COLORS_DIM" "$COLORS_NC"

  if [[ "$aws_done" == "yes" ]]; then
    cat <<EOF

Register the first account in the browser, then connect an account to scan:
${COLORS_BOLD}AWS Accounts → Add AWS Account${COLORS_NC} walks you through a CloudFormation stack
that grants TOPS a read-only audit role.
EOF
  else
    cat <<EOF

Register the first account in the browser. When you are ready to scan a real
AWS account, run this from ${COLORS_BOLD}$(pwd)${COLORS_NC}:

  ${COLORS_CYAN}./install.sh --aws-only${COLORS_NC}
EOF
  fi

  cat <<EOF

${COLORS_DIM}Useful commands:

  docker compose logs -f app     follow the application log
  docker compose down            stop TOPS (your data is kept)
  docker compose pull && docker compose up -d    upgrade in place${COLORS_NC}

EOF
}

main() {
  parse_args "$@"

  banner
  preflight

  if (( INSTALL_APP )); then
    if have_checkout; then
      log "Running inside an existing TOPS checkout."
      resolve_version
    else
      resolve_version
      fetch_release
    fi

    write_env
    start_stack
  else
    have_checkout || die "Run --aws-only from the TOPS directory the installer created."
  fi

  local aws_done="no"
  if [[ "$AWS_MODE" != "no" ]] && setup_aws; then
    aws_done="yes"
  fi

  if (( INSTALL_APP )); then
    summary "$aws_done"
  elif [[ "$aws_done" == "yes" ]]; then
    cat <<EOF

Restart TOPS so it picks up the new configuration:

  ${COLORS_CYAN}docker compose up -d${COLORS_NC}    ${COLORS_DIM}(or ./install-build.sh if you build from source)${COLORS_NC}

EOF
  fi
}

# Only install when executed. Sourcing this file defines the functions without
# running anything, which is how tests/install-secrets.test.sh exercises
# write_env against a throwaway directory. `bash <(curl ...)` still runs: both
# $0 and BASH_SOURCE are the same /dev/fd entry there.
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  main "$@"
fi
