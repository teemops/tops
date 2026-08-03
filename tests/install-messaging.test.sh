#!/usr/bin/env bash
#
# install-messaging.test.sh — how the AWS installer handles its inputs: reading
# .env, and reporting why AWS said no.
#
# Both halves are regressions from issue #56, and both are the same defect: a
# real failure hidden behind a wrong explanation.
#
#   1. The installer used to `source` /workspace/.env, which is a Compose env
#      file, not a shell script. The cron schedules in it are unquoted and
#      contain spaces, so bash ran them: TOPS_BACKUP_FULL_CRON=0 17 * * *
#      assigned `0` and then tried to execute `17`. Under `set -e` that killed
#      the install before any stack was deployed, and the only symptom the user
#      saw was a UI reporting a missing AWS_PARENT_ACCOUNT_ID.
#
#   2. Any failing `sts get-caller-identity` was then reported as "credentials
#      not configured, mount ~/.aws" — including the InvalidClientTokenId that a
#      region the account has not opted into returns for perfectly good
#      credentials. The true error went to the log and nowhere else.
#
# The .env checks load the shipped example unchanged, so they stay green when a
# future edit adds another value with a space in it, and fail loudly if
# load_dotenv goes back to executing the file.
#
# Nothing here touches AWS or the working tree: the CLI is stubbed.
#
#   Usage:  tests/install-messaging.test.sh

set -Eeuo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$HERE/.." && pwd)"
INSTALLER="$REPO_ROOT/docker/installer/scripts/install-messaging.sh"

c_red=$'\033[31m'; c_green=$'\033[32m'; c_bold=$'\033[1m'; c_dim=$'\033[2m'; c_off=$'\033[0m'
[[ -t 1 ]] || { c_red=; c_green=; c_bold=; c_dim=; c_off=; }

PASS=0; FAIL=0

step() { printf '\n%s── %s%s\n' "$c_bold" "$*" "$c_off"; }
info() { printf '%s   %s%s\n' "$c_dim" "$*" "$c_off"; }
pass() { printf '%s   PASS%s %s\n' "$c_green" "$c_off" "$*"; PASS=$(( PASS + 1 )); }
fail() { printf '%s   FAIL%s %s\n' "$c_red"   "$c_off" "$*"; FAIL=$(( FAIL + 1 )); }

WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

# Runs load_dotenv against an .env in a throwaway TEEMOPS_ROOT and prints one
# variable's value. A subshell per call, so nothing leaks between checks — and
# the installer's own `set -euo pipefail` applies, exactly as in the container.
load_and_get() {
  local root="$1" var="$2"
  (
    ROOT="$root"
    TEEMOPS_ROOT="$root"
    # shellcheck source=/dev/null
    source "$INSTALLER"
    load_dotenv
    printf '%s' "${!var-}"
  )
}

# ---------------------------------------------------------------------------
step "The shipped .env.docker.example loads without executing anything"

SHIPPED="$WORK/shipped"
mkdir -p "$SHIPPED"
cp "$REPO_ROOT/.env.docker.example" "$SHIPPED/.env"

# The exact line from issue #56: bash would assign `0` and run `17 * * *`.
if out="$(load_and_get "$SHIPPED" TOPS_BACKUP_FULL_CRON 2>"$WORK/shipped.err")"; then
  if [[ "$out" == "0 17 * * *" ]]; then
    pass "TOPS_BACKUP_FULL_CRON read literally as '$out'"
  else
    fail "TOPS_BACKUP_FULL_CRON is '$out', expected '0 17 * * *'"
  fi
else
  fail "load_dotenv aborted on the shipped .env.docker.example"
  cat "$WORK/shipped.err"
fi

if [[ -s "$WORK/shipped.err" ]]; then
  fail "load_dotenv wrote to stderr:"
  cat "$WORK/shipped.err"
else
  pass "load_dotenv is silent — nothing in the file was executed"
fi

# The other three schedules glob as well: `*/15 * * * *` expands against the
# workspace before bash even looks for a command.
for key in TOPS_BACKUP_DIFF_CRON TOPS_BACKUP_BINLOG_CRON TOPS_BACKUP_PRUNE_CRON; do
  expected="$(grep -E "^${key}=" "$REPO_ROOT/.env.docker.example" | head -n 1 | cut -d= -f2-)"
  actual="$(load_and_get "$SHIPPED" "$key")"
  if [[ "$actual" == "$expected" ]]; then
    pass "$key read literally as '$actual'"
  else
    fail "$key is '$actual', expected '$expected'"
  fi
done

# ---------------------------------------------------------------------------
step "Values are data, never commands"

HOSTILE="$WORK/hostile"
mkdir -p "$HOSTILE"
cat > "$HOSTILE/.env" <<'EOF'
# A comment, then a blank line.

TOPS_DEPLOYMENT_REGION=us-west-2
WITH_SPACES=0 17 * * *
WITH_SUBSHELL=$(touch /tmp/tops-dotenv-should-not-exist)
WITH_BACKTICKS=`touch /tmp/tops-dotenv-should-not-exist`
WITH_HASH=pass#word
DOUBLE_QUOTED="0 5 * * *"
SINGLE_QUOTED='0 5 * * *'
  INDENTED=indented
not a key=value
EOF

declare -A expected=(
  [TOPS_DEPLOYMENT_REGION]="us-west-2"
  [WITH_SPACES]="0 17 * * *"
  [WITH_SUBSHELL]='$(touch /tmp/tops-dotenv-should-not-exist)'
  [WITH_BACKTICKS]='`touch /tmp/tops-dotenv-should-not-exist`'
  [WITH_HASH]="pass#word"
  [DOUBLE_QUOTED]="0 5 * * *"
  [SINGLE_QUOTED]="0 5 * * *"
  [INDENTED]="indented"
)

for key in "${!expected[@]}"; do
  actual="$(load_and_get "$HOSTILE" "$key")"
  if [[ "$actual" == "${expected[$key]}" ]]; then
    pass "$key = '${actual}'"
  else
    fail "$key is '$actual', expected '${expected[$key]}'"
  fi
done

if [[ -e /tmp/tops-dotenv-should-not-exist ]]; then
  fail "a value in .env was executed — it created /tmp/tops-dotenv-should-not-exist"
  rm -f /tmp/tops-dotenv-should-not-exist
else
  pass "no value in .env was executed"
fi

# ---------------------------------------------------------------------------
step "A missing .env is not an error"

EMPTY="$WORK/empty"
mkdir -p "$EMPTY"
if (
  TEEMOPS_ROOT="$EMPTY"
  # shellcheck source=/dev/null
  source "$INSTALLER"
  load_dotenv
) >"$WORK/empty.log" 2>&1; then
  pass "load_dotenv succeeds when there is no .env"
else
  fail "load_dotenv failed when there is no .env"
  cat "$WORK/empty.log"
fi

# ---------------------------------------------------------------------------
step "The region the installer needs survives the round trip"

# require_region is what consumes load_dotenv's output, and TOPS_DEPLOYMENT_REGION
# sits below the cron block in .env — so before the fix it was never reached.
REGION="$WORK/region"
mkdir -p "$REGION"
cp "$REPO_ROOT/.env.docker.example" "$REGION/.env"
printf 'TOPS_DEPLOYMENT_REGION=%s\n' "us-west-2" >> "$REGION/.env"

if out="$(
  TEEMOPS_ROOT="$REGION"
  # shellcheck source=/dev/null
  source "$INSTALLER"
  load_dotenv
  require_region >/dev/null 2>&1
  printf '%s' "$AWS_DEFAULT_REGION"
)"; then
  if [[ "$out" == "us-west-2" ]]; then
    pass "require_region resolved '$out' from .env"
  else
    fail "require_region resolved '$out', expected 'us-west-2'"
  fi
else
  fail "require_region aborted — TOPS_DEPLOYMENT_REGION never made it out of .env"
fi

# ---------------------------------------------------------------------------
step "A failing AWS call reports what AWS actually said"

STUB="$WORK/stub"
mkdir -p "$STUB"
stub_aws() { printf '%s\n' "#!/usr/bin/env bash" "$1" > "$STUB/aws"; chmod +x "$STUB/aws"; }

AUTH="$WORK/auth"
mkdir -p "$AUTH"
printf 'TOPS_DEPLOYMENT_REGION=ap-southeast-6\n' > "$AUTH/.env"

# validate_aws_auth calls die, which exits 1 — so capture, do not propagate.
run_auth() {
  PATH="$STUB:$PATH" bash -c '
    TEEMOPS_ROOT="'"$AUTH"'"
    # shellcheck source=/dev/null
    source "'"$INSTALLER"'"
    load_dotenv
    require_region
    validate_aws_auth
  ' 2>&1 || true
}

# What issue #56's reporter hit: valid keys, a region the account has not
# enabled. AWS calls that InvalidClientTokenId, which is not a credentials fault.
stub_aws 'echo "An error occurred (InvalidClientTokenId) when calling the GetCallerIdentity operation: The security token included in the request is invalid." >&2
exit 255'
out="$(run_auth)"

if [[ "$out" == *InvalidClientTokenId* ]]; then
  pass "the real AWS error reaches the terminal"
else
  fail "AWS's error was swallowed: $out"
fi

if [[ "$out" == *ap-southeast-6* ]]; then
  pass "the message names the region that was rejected"
else
  fail "the message does not name the region: $out"
fi

if [[ "$out" == *"not enabled for this account"* ]]; then
  pass "the message explains an opt-in region, not a bad credential"
else
  fail "the message does not mention region enablement: $out"
fi

if [[ "$out" != *"Mount ~/.aws"* ]]; then
  pass "it does not send the user to check their ~/.aws mount"
else
  fail "still blaming the ~/.aws mount for a region problem: $out"
fi

# A genuinely missing credential still gets the mount hint — and still shows
# what AWS said, so the two cases are told apart by evidence rather than a guess.
stub_aws 'echo "Unable to locate credentials. You can configure credentials by running \"aws configure\"." >&2
exit 255'
out="$(run_auth)"

if [[ "$out" == *"Unable to locate credentials"* ]]; then
  pass "a missing credential also reports AWS's own wording"
else
  fail "AWS's error was swallowed: $out"
fi

if [[ "$out" == *"Mount ~/.aws"* ]]; then
  pass "a missing credential still gets the mount hint"
else
  fail "the mount hint is gone for a real credentials failure: $out"
fi

# ---------------------------------------------------------------------------
step "Every CloudFormation output the installer requires is actually exported"

# write_env_file treats a missing output as fatal, and it runs *after* both
# stacks have deployed — so a template that forgets to export one costs a full
# deploy before failing. That is exactly what happened with TopsMainDlqName:
# sqs.cfn.yaml exported it, the parent template.yaml did not pass it up, and the
# install died at the last step with both stacks already created.
#
# The required keys are read out of the installer itself rather than listed
# here, so adding a cf_output call without an Output cannot pass unnoticed.
CORE_TEMPLATE="$REPO_ROOT/infra/cloud-stack/core-docker/template.yaml"
SNS_TEMPLATE="$REPO_ROOT/infra/cloud-stack/stackset/sns.topic.cfn.yaml"

# Output keys are the two-space-indented mapping keys under `Outputs:`.
template_exports() {
  sed -n '/^Outputs:/,/^[A-Za-z]/p' "$1" | grep -oE '^  [A-Za-z0-9]+:' | tr -d ' :'
}

required_from() {
  grep -oE "cf_output \"\\\$${1}\" [A-Za-z0-9]+" "$INSTALLER" | awk '{print $3}'
}

for stack in CORE:"$CORE_TEMPLATE" SNS:"$SNS_TEMPLATE"; do
  var="${stack%%:*}_STACK"
  template="${stack#*:}"
  exported="$(template_exports "$template")"
  required="$(required_from "$var")"

  if [[ -z "$required" ]]; then
    fail "no cf_output calls found for \$$var — the test can no longer see the contract"
    continue
  fi

  while read -r key; do
    [[ -n "$key" ]] || continue
    if grep -qx "$key" <<<"$exported"; then
      pass "$(basename "$template") exports $key"
    else
      fail "$(basename "$template") does not export $key, but the installer requires it"
    fi
  done <<<"$required"
done

# ---------------------------------------------------------------------------
step "The install id is minted once and never regenerated"

# N-11 phase 2. Regenerating this on a re-run of `install.sh --aws-only` would
# invalidate every onboarding link already issued *and* silently filter out the
# Delete ping from every account already linked — unlinking would stop working
# with no error anywhere. There is no AWS-side record to recover it from, so the
# reuse path is the only thing standing between a routine re-run and that.

# Runs resolve_install_id in a throwaway root and prints one variable.
resolve_and_get() {
  local root="$1" var="$2"
  (
    ROOT="$root"
    TEEMOPS_ROOT="$root"
    # shellcheck source=/dev/null
    source "$INSTALLER"
    load_dotenv
    resolve_install_id >/dev/null 2>&1
    printf '%s' "${!var-}"
  )
}

FRESH="$WORK/install-id-fresh"
mkdir -p "$FRESH/generated"

first="$(resolve_and_get "$FRESH" TOPS_INSTALL_ID)"
if [[ "$first" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$ ]]; then
  pass "a fresh install mints a v4 UUID ($first)"
else
  fail "a fresh install produced '$first', which is not a v4 UUID"
fi

# Simulate what write_env_file leaves behind, then re-run as `--aws-only` would.
printf 'TOPS_SNS_ARN=arn:aws:sns:us-west-2:1234:teemops-sns\nTOPS_INSTALL_ID=%s\n' "$first" \
  > "$FRESH/generated/teemops.env"

second="$(resolve_and_get "$FRESH" TOPS_INSTALL_ID)"
if [[ "$second" == "$first" ]]; then
  pass "a re-run reuses the id from generated/teemops.env"
else
  fail "a re-run changed the install id from '$first' to '$second' — every onboarding link would break"
fi

# An explicit value in .env is the rotation path and has to win.
ROTATE="$WORK/install-id-rotate"
mkdir -p "$ROTATE/generated"
printf 'TOPS_INSTALL_ID=%s\n' "$first" > "$ROTATE/generated/teemops.env"
printf 'TOPS_INSTALL_ID=%s\nTOPS_INSTALL_ID_PREVIOUS=%s\n' "new-id-0000" "$first" > "$ROTATE/.env"

rotated="$(resolve_and_get "$ROTATE" TOPS_INSTALL_ID)"
if [[ "$rotated" == "new-id-0000" ]]; then
  pass "TOPS_INSTALL_ID in .env overrides the stored value"
else
  fail "rotation ignored: got '$rotated', expected 'new-id-0000'"
fi

# Both ids reach the filter policy during a rotation, so onboarding already in
# flight against the old id still completes.
rotated_list="$(resolve_and_get "$ROTATE" TOPS_INSTALL_IDS)"
if [[ "$rotated_list" == "new-id-0000,${first}" ]]; then
  pass "the filter policy receives both ids while rotating"
else
  fail "filter policy list is '$rotated_list', expected 'new-id-0000,${first}'"
fi

single_list="$(resolve_and_get "$FRESH" TOPS_INSTALL_IDS)"
if [[ "$single_list" == "$first" ]]; then
  pass "without a rotation in progress only the current id is accepted"
else
  fail "filter policy list is '$single_list', expected '$first'"
fi

# ---------------------------------------------------------------------------
step "The install id reaches the SNS stack and the child templates"

if grep -q 'TopsInstallIds=' "$INSTALLER"; then
  pass "the installer passes TopsInstallIds to the SNS stack"
else
  fail "the installer never passes TopsInstallIds — the filter policy would deploy empty"
fi

if grep -q 'TOPS_INSTALL_ID=' "$INSTALLER"; then
  pass "the installer records TOPS_INSTALL_ID in generated/teemops.env"
else
  fail "the installer does not persist TOPS_INSTALL_ID — the next re-run would mint a new one"
fi

# One template, and it should stay that way. The audit variant was deleted in #102:
# it defaulted ParentAWSAccountId to the vendor's own AWS account, so deploying it
# unmodified created a cross-account role trusting an account the operator did not
# control. Nothing referenced it.
for template in "$REPO_ROOT/templates/iam.role.child.account.cfn.yaml"; do
  name="$(basename "$template")"

  if grep -q 'TopsInstallId: !Ref TopsInstallId' "$template"; then
    pass "$name passes TopsInstallId into the custom resource"
  else
    fail "$name does not put TopsInstallId in the message body — its ping would be filtered out"
  fi

  # NoEcho does not make it secret (it is readable via DescribeStacks by anyone
  # with the permission the TOPS role itself grants), but leaving it off would
  # print the value in console output for no benefit at all.
  if awk '/^  TopsInstallId:/{found=1} found && /NoEcho: true/{print; exit}' "$template" | grep -q NoEcho; then
    pass "$name marks TopsInstallId NoEcho"
  else
    fail "$name does not mark TopsInstallId NoEcho"
  fi
done

# A template with a Default on the parent account is deployable by a stranger against
# an account they do not own — the defect #102 was filed for. Guard the whole
# directory rather than the one file, so a future template cannot reintroduce it.
for template in "$REPO_ROOT"/templates/*.cfn.yaml; do
  name="$(basename "$template")"

  if awk '/^  ParentAWSAccountId:/{f=1; next} f && /^  [A-Za-z]/{exit} f && /Default:/{print; exit}' "$template" | grep -q Default; then
    fail "$name gives ParentAWSAccountId a Default — it would deploy against whatever account is baked in"
  else
    pass "$name makes ParentAWSAccountId explicit"
  fi
done

# The dead condition this replaces must not come back as live YAML. Comments are
# stripped first: the template explains at length why sns:MessageAttributes cannot
# work, and that prose is the point — it is what stops the line being revived.
if sed 's/#.*//' "$SNS_TEMPLATE" | grep -q 'sns:MessageAttributes'; then
  fail "sns:MessageAttributes is live in the SNS template — sns:Publish supports no such condition key"
else
  pass "the dead sns:MessageAttributes condition is gone"
fi

# ---------------------------------------------------------------------------
printf '\n%s%d passed, %d failed%s\n\n' "$c_bold" "$PASS" "$FAIL" "$c_off"
(( FAIL == 0 )) || exit 1
