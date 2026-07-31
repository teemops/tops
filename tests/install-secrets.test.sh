#!/usr/bin/env bash
#
# install-secrets.test.sh — proves the database passwords are generated at
# install time and never shipped in the repo.
#
# The failure this guards against is silent: an install that "works" with a
# password anyone can read out of a public repo looks exactly like an install
# with a real one. So each check asserts something observable — the value in
# .env differs from the example, survives a re-run, and Compose refuses to
# start without it.
#
# Nothing here touches the working tree: every run happens in a throwaway
# directory, and no container is started.
#
#   Usage:  tests/install-secrets.test.sh

set -Eeuo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$HERE/.." && pwd)"

c_red=$'\033[31m'; c_green=$'\033[32m'; c_bold=$'\033[1m'; c_dim=$'\033[2m'; c_off=$'\033[0m'
[[ -t 1 ]] || { c_red=; c_green=; c_bold=; c_dim=; c_off=; }

PASS=0; FAIL=0

step() { printf '\n%s── %s%s\n' "$c_bold" "$*" "$c_off"; }
info() { printf '%s   %s%s\n' "$c_dim" "$*" "$c_off"; }
pass() { printf '%s   PASS%s %s\n' "$c_green" "$c_off" "$*"; PASS=$(( PASS + 1 )); }
fail() { printf '%s   FAIL%s %s\n' "$c_red"   "$c_off" "$*"; FAIL=$(( FAIL + 1 )); }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

SECRETS=(MYSQL_ROOT_PASSWORD MYSQL_PASSWORD TOPS_BACKUP_PASSWORD)

# Reads a variable out of an env file the same way Compose does: first match wins.
env_value() { grep -E "^${2}=" "$1" | head -n 1 | cut -d= -f2- || true; }

# ---------------------------------------------------------------------------
step "The repo ships no password values"

for key in "${SECRETS[@]}" DB_PASSWORD APP_KEY; do
  value="$(env_value "$REPO_ROOT/.env.docker.example" "$key")"
  if [[ -z "$value" ]]; then
    pass "$key is blank in .env.docker.example"
  else
    fail "$key has a value in .env.docker.example: $value"
  fi
done

# ---------------------------------------------------------------------------
step "install.sh generates each password on a fresh .env"

# Sourcing rather than executing: write_env is the unit under test, and a real
# run would download a release and start containers.
FRESH="$WORK/fresh"
mkdir -p "$FRESH"
cp "$REPO_ROOT/.env.docker.example" "$FRESH/.env.docker.example"

TOPS_VERSION="0.0.0-test"
(
  cd "$FRESH"
  # write_env creates ~/.aws; keep that inside the throwaway directory.
  export HOME="$FRESH/home"
  mkdir -p "$HOME"
  # shellcheck source=/dev/null
  source "$REPO_ROOT/install.sh"
  write_env
) >"$WORK/fresh.log" 2>&1 || { fail "install.sh write_env failed"; cat "$WORK/fresh.log"; }

for key in "${SECRETS[@]}"; do
  value="$(env_value "$FRESH/.env" "$key")"
  example="$(env_value "$REPO_ROOT/.env.docker.example" "$key")"
  if [[ -n "$value" && "$value" != "$example" && ${#value} -ge 20 ]]; then
    pass "$key generated (${#value} chars)"
  else
    fail "$key was not generated: '$value'"
  fi
done

if [[ "$(env_value "$FRESH/.env" DB_PASSWORD)" == "$(env_value "$FRESH/.env" MYSQL_PASSWORD)" ]]; then
  pass "DB_PASSWORD matches MYSQL_PASSWORD"
else
  fail "DB_PASSWORD and MYSQL_PASSWORD disagree — the app cannot log in"
fi

# Three independent secrets, not one value written three times.
uniques="$(for key in "${SECRETS[@]}"; do env_value "$FRESH/.env" "$key"; done | sort -u | wc -l)"
if [[ "$uniques" -eq 3 ]]; then
  pass "the three passwords are distinct"
else
  fail "expected 3 distinct passwords, got $uniques"
fi

# ---------------------------------------------------------------------------
step "Re-running install.sh leaves them alone"

# MySQL stores the root and user passwords in its datadir on first init, so a
# regenerated value locks the owner out of their own database.
before="$(cat "$FRESH/.env")"
(
  cd "$FRESH"
  export HOME="$FRESH/home"
  # shellcheck source=/dev/null
  source "$REPO_ROOT/install.sh"
  write_env
) >"$WORK/rerun.log" 2>&1 || { fail "install.sh write_env failed on re-run"; cat "$WORK/rerun.log"; }

for key in "${SECRETS[@]}" DB_PASSWORD APP_KEY; do
  if [[ "$(env_value "$FRESH/.env" "$key")" == "$(printf '%s' "$before" | grep -E "^${key}=" | head -n 1 | cut -d= -f2-)" ]]; then
    pass "$key unchanged"
  else
    fail "$key changed on re-run"
  fi
done

# ---------------------------------------------------------------------------
step "A hand-edited .env missing the keys entirely still gets them"

# The keys absent rather than blank. grep exits 1 on no match, and under
# `set -o pipefail` that aborts the installer unless it is handled — so this
# covers a real path, not a hypothetical one.
SPARSE="$WORK/sparse"
mkdir -p "$SPARSE/home"
grep -vE '^(MYSQL_ROOT_PASSWORD|MYSQL_PASSWORD|TOPS_BACKUP_PASSWORD|DB_PASSWORD)=' \
  "$REPO_ROOT/.env.docker.example" > "$SPARSE/.env"

if (
  cd "$SPARSE"
  export HOME="$SPARSE/home"
  # shellcheck source=/dev/null
  source "$REPO_ROOT/install.sh"
  write_env
) >"$WORK/sparse.log" 2>&1; then
  pass "install.sh survived an .env with the keys absent"
else
  fail "install.sh aborted on an .env with the keys absent"
  cat "$WORK/sparse.log"
fi

for key in "${SECRETS[@]}" DB_PASSWORD; do
  value="$(env_value "$SPARSE/.env" "$key")"
  if [[ -n "$value" ]]; then
    pass "$key appended"
  else
    fail "$key missing after write_env"
  fi
done

# ---------------------------------------------------------------------------
step "prepare-build.sh generates the same passwords the same way"

# The contributor path. Run against a copy of the repo skeleton so the real
# .env is never touched, with composer and npm stubbed — this test is about the
# env block, not the asset build.
BUILD="$WORK/build"
mkdir -p "$BUILD/docker/scripts" "$BUILD/app" "$WORK/stub"
cp "$REPO_ROOT/docker/scripts/prepare-build.sh" "$BUILD/docker/scripts/"
cp "$REPO_ROOT/.env.docker.example" "$BUILD/.env"

for cmd in composer npm; do
  printf '#!/usr/bin/env bash\nexit 0\n' > "$WORK/stub/$cmd"
  chmod +x "$WORK/stub/$cmd"
done

PATH="$WORK/stub:$PATH" "$BUILD/docker/scripts/prepare-build.sh" \
  >"$WORK/build.log" 2>&1 || { fail "prepare-build.sh failed"; cat "$WORK/build.log"; }

for key in "${SECRETS[@]}"; do
  value="$(env_value "$BUILD/.env" "$key")"
  if [[ -n "$value" && ${#value} -ge 20 ]]; then
    pass "$key generated by prepare-build.sh"
  else
    fail "$key was not generated by prepare-build.sh: '$value'"
  fi
done

if [[ "$(env_value "$BUILD/.env" DB_PASSWORD)" == "$(env_value "$BUILD/.env" MYSQL_PASSWORD)" ]]; then
  pass "DB_PASSWORD matches MYSQL_PASSWORD"
else
  fail "DB_PASSWORD and MYSQL_PASSWORD disagree"
fi

# A file holding real passwords should not be readable by every account on the
# host. install.sh gets this for free by writing through mktemp.
for env_path in "$FRESH/.env" "$BUILD/.env"; do
  mode="$(stat -c '%a' "$env_path")"
  if [[ "$mode" == "600" ]]; then
    pass "$(basename "$(dirname "$env_path")")/.env is 0600"
  else
    fail "$(basename "$(dirname "$env_path")")/.env is $mode, not 0600"
  fi
done

# The two installers must produce different secrets on different machines.
if [[ "$(env_value "$BUILD/.env" MYSQL_ROOT_PASSWORD)" != "$(env_value "$FRESH/.env" MYSQL_ROOT_PASSWORD)" ]]; then
  pass "a second install gets its own passwords"
else
  fail "two installs produced the same MYSQL_ROOT_PASSWORD"
fi

# ---------------------------------------------------------------------------
step "Compose refuses to start without a password"

if ! docker compose version >/dev/null 2>&1; then
  info "docker compose not available — skipping"
else
  for key in "${SECRETS[@]}"; do
    # Everything present except the one under test: Compose must fail on it
    # rather than falling back to a default.
    grep -vE "^${key}=" "$FRESH/.env" > "$WORK/compose.env"

    if docker compose --env-file "$WORK/compose.env" -f "$REPO_ROOT/docker-compose.yml" \
         config >/dev/null 2>"$WORK/compose.err"; then
      fail "compose config succeeded with $key unset"
    elif grep -q "$key" "$WORK/compose.err"; then
      pass "compose fails on missing $key"
    else
      fail "compose failed, but not because of $key: $(cat "$WORK/compose.err")"
    fi
  done
fi

# ---------------------------------------------------------------------------
printf '\n%s%d passed, %d failed%s\n' "$c_bold" "$PASS" "$FAIL" "$c_off"
[[ "$FAIL" -eq 0 ]]
