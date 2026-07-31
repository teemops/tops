#!/usr/bin/env bash
#
# install-flow.test.sh — covers the one-script install flow: the argument modes,
# the AWS credential check, and the region resolution that feeds it.
#
# The failure these guard against is a script that looks fine when you run it on
# a machine that already works. The interesting paths are the ones a first-time
# user hits — no AWS CLI, a misconfigured CLI, no terminal to prompt on — and
# each of those must produce a clear message rather than a hang, a stack trace,
# or a silent skip.
#
# Nothing here starts a container or calls AWS: the `aws` CLI is stubbed.
#
#   Usage:  tests/install-flow.test.sh

set -Eeuo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$HERE/.." && pwd)"
INSTALLER="$REPO_ROOT/install.sh"

c_red=$'\033[31m'; c_green=$'\033[32m'; c_bold=$'\033[1m'; c_dim=$'\033[2m'; c_off=$'\033[0m'
[[ -t 1 ]] || { c_red=; c_green=; c_bold=; c_dim=; c_off=; }

PASS=0; FAIL=0

step() { printf '\n%s── %s%s\n' "$c_bold" "$*" "$c_off"; }
info() { printf '%s   %s%s\n' "$c_dim" "$*" "$c_off"; }
pass() { printf '%s   PASS%s %s\n' "$c_green" "$c_off" "$*"; PASS=$(( PASS + 1 )); }
fail() { printf '%s   FAIL%s %s\n' "$c_red"   "$c_off" "$*"; FAIL=$(( FAIL + 1 )); }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# Runs a snippet with install.sh's functions available. Sourcing rather than
# executing, because a real run downloads a release and starts containers.
in_installer() { ( cd "${2:-$WORK}"; . "$INSTALLER"; eval "$1" ); }

# ---------------------------------------------------------------------------
step "There is exactly one installer entry point"

if [[ -f "$REPO_ROOT/install-messaging.sh" ]]; then
  fail "install-messaging.sh still exists — the second script is what confused users"
else
  pass "install-messaging.sh is gone"
fi

for doc in README.md docker-compose.README.md DEBUG.md install-build.sh; do
  if grep -q 'install-messaging\.sh' "$REPO_ROOT/$doc" 2>/dev/null; then
    fail "$doc still points users at install-messaging.sh"
  else
    pass "$doc points at install.sh"
  fi
done

# ---------------------------------------------------------------------------
step "The argument modes select the right work"

if bash "$INSTALLER" --help | grep -q -- '--aws-only'; then
  pass "--help documents --aws-only"
else
  fail "--help does not document the modes"
fi

if bash "$INSTALLER" --nonsense >/dev/null 2>&1; then
  fail "an unknown option was accepted"
else
  pass "an unknown option exits non-zero"
fi

check_mode() {
  local args="$1" expect_mode="$2" expect_install="$3" got
  got="$(in_installer "parse_args $args; printf '%s %s' \"\$AWS_MODE\" \"\$INSTALL_APP\"")"
  if [[ "$got" == "$expect_mode $expect_install" ]]; then
    pass "'${args:-<no args>}' → AWS_MODE=$expect_mode INSTALL_APP=$expect_install"
  else
    fail "'${args:-<no args>}' → got '$got', expected '$expect_mode $expect_install'"
  fi
}

check_mode ""           ask 1
check_mode "--aws"      yes 1
check_mode "--no-aws"   no  1
check_mode "--aws-only" yes 0

# ---------------------------------------------------------------------------
step "The AWS credential check"

# A stub `aws` on PATH, so no real call is made and no credentials are needed.
STUB="$WORK/stub"
mkdir -p "$STUB"
stub_aws() { printf '%s\n' "#!/usr/bin/env bash" "$1" > "$STUB/aws"; chmod +x "$STUB/aws"; }

# The documented failure: CLI present, credentials not usable.
stub_aws 'exit 255'
if out="$(PATH="$STUB:$PATH" in_installer 'check_aws_identity' 2>&1)"; then
  fail "check_aws_identity succeeded against a failing CLI"
elif [[ "$out" == *"check your AWS CLI configuration"* ]]; then
  pass "a failing 'aws sts get-caller-identity' is reported, not swallowed"
else
  fail "unhelpful message on a failing CLI: $out"
fi

# No CLI at all. `env -i` gives a PATH without aws, and without the caller's
# AWS_* variables leaking in.
if out="$(env -i PATH=/usr/bin:/bin HOME="$WORK" bash -c ". '$INSTALLER'; check_aws_identity" 2>&1)"; then
  fail "check_aws_identity succeeded with no AWS CLI installed"
elif [[ "$out" == *"AWS CLI is not installed"* ]]; then
  pass "a missing AWS CLI is reported, not swallowed"
else
  fail "unhelpful message with no AWS CLI: $out"
fi

# The happy path: account id and ARN come back on one tab-separated line.
stub_aws 'printf "123456789012\tarn:aws:iam::123456789012:user/ben\n"'
if out="$(PATH="$STUB:$PATH" in_installer 'check_aws_identity; printf "[%s]" "$AWS_ACCOUNT_ID"' 2>&1)"; then
  if [[ "$out" == *"[123456789012]"* ]]; then
    pass "the account id is parsed out of get-caller-identity"
  else
    fail "account id not parsed: $out"
  fi
  if [[ "$out" == *"arn:aws:iam::123456789012:user/ben"* ]]; then
    pass "the caller ARN is shown, so you can see which identity you are using"
  else
    fail "caller ARN not shown: $out"
  fi
else
  fail "check_aws_identity failed against a working CLI: $out"
fi

# ---------------------------------------------------------------------------
step "Region resolution"

ENVDIR="$WORK/envdir"
mkdir -p "$ENVDIR"
printf 'APP_PORT=8080\n' > "$ENVDIR/.env"

# .env wins, so a re-run does not re-ask. There is no terminal here, which is
# also the `curl | bash` case — it must resolve or fail, never block.
printf 'TOPS_DEPLOYMENT_REGION=ap-southeast-2\n' >> "$ENVDIR/.env"
got="$(in_installer 'resolve_aws_region; printf "%s" "$AWS_REGION_RESOLVED"' "$ENVDIR" </dev/null)"
if [[ "$got" == "ap-southeast-2" ]]; then
  pass "the region already in .env is reused"
else
  fail "expected ap-southeast-2 from .env, got '$got'"
fi

# The region is the one value a user types that reaches both .env and a sed
# replacement, so a shell/sed metacharacter must be rejected rather than
# corrupting the file.
printf 'TOPS_DEPLOYMENT_REGION=us-east-1|oops\n' > "$ENVDIR/.env.bad"
if in_installer 'env_get .env.bad TOPS_DEPLOYMENT_REGION >/dev/null' "$ENVDIR" >/dev/null 2>&1; then
  cp "$ENVDIR/.env" "$ENVDIR/.env.keep"
  cp "$ENVDIR/.env.bad" "$ENVDIR/.env"
  if in_installer 'resolve_aws_region' "$ENVDIR" </dev/null >/dev/null 2>&1; then
    fail "a region containing '|' was accepted"
  else
    pass "a region that is not shaped like a region is rejected"
  fi
  mv "$ENVDIR/.env.keep" "$ENVDIR/.env"
fi

# Nothing configured anywhere: must report failure rather than deploy to a
# guessed region.
grep -v '^TOPS_DEPLOYMENT_REGION=' "$ENVDIR/.env" > "$ENVDIR/.env.tmp"
mv "$ENVDIR/.env.tmp" "$ENVDIR/.env"
stub_aws 'exit 1'
if env -i PATH="$STUB:/usr/bin:/bin" HOME="$WORK" bash -c \
     "cd '$ENVDIR'; . '$INSTALLER'; resolve_aws_region" </dev/null >/dev/null 2>&1; then
  fail "resolve_aws_region reported success with no region anywhere"
else
  pass "no region anywhere is a failure, not a guess"
fi

# ---------------------------------------------------------------------------
step "No prompt can hang a non-interactive install"

# `bash <(curl ...)` keeps a terminal, but piping into bash does not. A prompt
# that blocks there would hang CI and every scripted install.
if timeout 10 bash -c ". '$INSTALLER'; confirm 'proceed?'" </dev/null >/dev/null 2>&1; then
  fail "confirm said yes without a terminal"
elif (( $? == 124 )); then
  fail "confirm blocked waiting for input with no terminal"
else
  pass "confirm declines instead of blocking when there is no terminal"
fi

# ---------------------------------------------------------------------------
step "Presentation"

# Colour off when stdout is not a terminal, so logs and CI output stay readable.
if [[ -z "$(in_installer 'printf "%s" "$COLORS_RED"')" ]]; then
  pass "colour is suppressed when stdout is not a terminal"
else
  fail "escape codes are emitted into a non-terminal stdout"
fi

# The banner is box-drawing characters; on a non-UTF-8 terminal they arrive as
# mojibake, which is a worse first impression than plain text.
got="$(env -i PATH=/usr/bin:/bin HOME="$WORK" LANG=C bash -c ". '$INSTALLER'; banner")"
if [[ "$got" == *"█"* ]]; then
  fail "the block banner is printed on a non-UTF-8 terminal"
elif [[ "$got" == *"T O P S"* ]]; then
  pass "a plain banner is used on a non-UTF-8 terminal"
else
  fail "no banner at all on a non-UTF-8 terminal: $got"
fi

# ---------------------------------------------------------------------------
step "The AWS step's compose overlay is valid"

if ! docker compose version >/dev/null 2>&1; then
  info "docker compose not available — skipping"
else
  # A real .env, so the ${VAR:?} guards in docker-compose.yml are satisfied and
  # the only thing under test is the installer service.
  CFG="$WORK/cfg"
  mkdir -p "$CFG/home"
  cp "$REPO_ROOT/.env.docker.example" "$CFG/.env.docker.example"
  ( cd "$CFG"; export HOME="$CFG/home"; TOPS_VERSION=0.0.0-test; . "$INSTALLER"; write_env ) \
    >"$WORK/cfg.log" 2>&1 || { fail "write_env failed"; cat "$WORK/cfg.log"; }

  if out="$(docker compose --env-file "$CFG/.env" \
        -f "$REPO_ROOT/docker-compose.yml" -f "$REPO_ROOT/docker-compose.install.yml" \
        --profile install config 2>&1)"; then
    pass "docker-compose.install.yml is valid alongside docker-compose.yml"
  else
    fail "compose config failed: $out"
  fi

  # install.sh pulls the published installer image rather than building it, so
  # the service has to name one.
  if printf '%s' "$out" | grep -q 'teem/tops-installer'; then
    pass "the installer service resolves to a published image"
  else
    fail "the installer service has no image to pull — install.sh would build it every time"
  fi
fi

# ---------------------------------------------------------------------------
printf '\n%s%d passed, %d failed%s\n' "$c_bold" "$PASS" "$FAIL" "$c_off"
[[ "$FAIL" -eq 0 ]]
