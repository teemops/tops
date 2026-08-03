#!/usr/bin/env bash
# Deploy Teemops messaging (SQS + S3 via SAM, SNS via CloudFormation) and write generated/teemops.env
set -euo pipefail

ROOT="${TEEMOPS_ROOT:-/workspace}"
CORE_DOCKER_DIR="${ROOT}/infra/cloud-stack/core-docker"
SNS_TEMPLATE="${ROOT}/infra/cloud-stack/stackset/sns.topic.cfn.yaml"
ENV_FILE="${ROOT}/generated/teemops.env"
LOG_FILE="${ROOT}/generated/install.log"

mkdir -p "${ROOT}/generated"

log() {
  echo "[installer] $*" | tee -a "$LOG_FILE"
}

die() {
  log "ERROR: $*"
  exit 1
}

# .env is a Compose env file, not a shell script: its values are literal, and
# unquoted values containing spaces are normal there. `source` executed them.
# The cron schedules are the case that bit us — TOPS_BACKUP_FULL_CRON=0 17 * * *
# assigned `0`, then ran `17` with the `*` globbed against /workspace, and under
# `set -e` the whole install died before deploying a single stack. The user saw
# "line 80: 17: command not found" and then a UI reporting a missing
# AWS_PARENT_ACCOUNT_ID, because generated/teemops.env was never written.
#
# So parse the file the way Compose reads it: KEY=rest-of-line, taken literally,
# with at most one layer of surrounding quotes removed. Nothing is executed.
#
# Literal means TOPS_BACKUP_DIR=${HOME}/.tops/backups stays unexpanded — that is
# the only such value in the file, Compose expands it itself when it reads .env
# for interpolation, and nothing in this script consumes it.
load_dotenv() {
  [[ -f "${ROOT}/.env" ]] || return 0

  local line key value
  while IFS= read -r line || [[ -n "$line" ]]; do
    # Blanks, comments, and anything that is not a KEY=VALUE assignment.
    if [[ ! "$line" =~ ^[[:space:]]*([A-Za-z_][A-Za-z0-9_]*)=(.*)$ ]]; then
      continue
    fi
    key="${BASH_REMATCH[1]}"
    value="${BASH_REMATCH[2]}"

    if [[ "$value" =~ ^\"(.*)\"$ || "$value" =~ ^\'(.*)\'$ ]]; then
      value="${BASH_REMATCH[1]}"
    fi

    export "${key}=${value}"
  done < "${ROOT}/.env"
}

require_region() {
  export AWS_DEFAULT_REGION="${TOPS_DEPLOYMENT_REGION:?Set TOPS_DEPLOYMENT_REGION in .env or environment}"
  export AWS_REGION="$AWS_DEFAULT_REGION"
  log "Using AWS region: $AWS_DEFAULT_REGION"
}

validate_aws_auth() {
  local err_file="${ROOT}/generated/sts-error.log" err
  if ! aws sts get-caller-identity --output json \
      > "${ROOT}/generated/caller-identity.json" 2>"$err_file"; then
    err="$(tr -d '\r' < "$err_file" | grep -v '^[[:space:]]*$' | tail -n 3)"
    cat "$err_file" >> "$LOG_FILE"

    # A region the account has not opted into rejects perfectly good credentials
    # with InvalidClientTokenId. That reads as a credentials problem and is not
    # one — it sent the reporter of issue #56 looking at their ~/.aws mount
    # while the real answer was that they had typed an opt-in region. Whatever
    # the cause, show what AWS actually said rather than guessing for it.
    if [[ "$err" == *InvalidClientTokenId* ]]; then
      die "AWS rejected these credentials in ${AWS_DEFAULT_REGION}.
That usually means the region is not enabled for this account rather than that
the credentials are wrong — opt-in regions (ap-southeast-3 and up, ap-east-*,
me-*, af-*, il-*, eu-south-*) must be enabled under Account → AWS Regions in the
console. Pick a region you already use, or enable this one, then re-run.
AWS said: ${err}"
    fi

    die "Could not authenticate with AWS. Mount ~/.aws, or set AWS_ACCESS_KEY_ID
and AWS_SECRET_ACCESS_KEY. AWS said: ${err}"
  fi
  local account_id
  account_id="$(python3 -c "import json; print(json.load(open('${ROOT}/generated/caller-identity.json'))['Account'])")"
  log "Authenticated as AWS account $account_id"
  export DETECTED_AWS_ACCOUNT_ID="$account_id"
}

cf_output() {
  local stack_name="$1"
  local output_key="$2"
  aws cloudformation describe-stacks \
    --stack-name "$stack_name" \
    --region "$AWS_DEFAULT_REGION" \
    --query "Stacks[0].Outputs[?OutputKey=='${output_key}'].OutputValue | [0]" \
    --output text 2>>"$LOG_FILE"
}

deploy_core_docker() {
  local environment="${TOPS_ENVIRONMENT:-test}"
  local stack_name="${TOPS_CORE_DOCKER_STACK:-teemops-core-docker}"

  log "Building SAM package (core-docker)..."
  (cd "$CORE_DOCKER_DIR" && sam build --cached --parallel 2>&1 | tee -a "$LOG_FILE")

  log "Deploying stack ${stack_name}..."
  (cd "$CORE_DOCKER_DIR" && sam deploy \
    --stack-name "$stack_name" \
    --region "$AWS_DEFAULT_REGION" \
    --no-confirm-changeset \
    --no-fail-on-empty-changeset \
    --capabilities CAPABILITY_IAM CAPABILITY_NAMED_IAM CAPABILITY_AUTO_EXPAND \
    --parameter-overrides "Environment=${environment}" \
    2>&1 | tee -a "$LOG_FILE")

  export CORE_STACK="$stack_name"
}

# Read one KEY's value back out of a previously written generated/teemops.env.
#
# Deliberately not `source` — same reasoning as load_dotenv above.
env_file_value() {
  local key="$1" line
  [[ -f "$ENV_FILE" ]] || return 0

  while IFS= read -r line || [[ -n "$line" ]]; do
    if [[ "$line" =~ ^[[:space:]]*${key}=(.*)$ ]]; then
      printf '%s' "${BASH_REMATCH[1]}"
      return 0
    fi
  done < "$ENV_FILE"
}

# The install-scoped filter secret (N-11).
#
# This must NEVER be regenerated once an installation has one. Minting a fresh
# GUID on a re-run of `install.sh --aws-only` would invalidate every onboarding
# link already issued *and* silently filter out the Delete ping from every account
# already linked — so unlinking would stop working with no error anywhere. That is
# the single worst thing this script could do, hence the explicit precedence:
#
#   1. TOPS_INSTALL_ID set in .env or the environment — the manual rotation path.
#   2. Whatever the last successful install wrote to generated/teemops.env.
#   3. Only then, a new one.
#
# generated/teemops.env is rewritten wholesale by write_env_file, so step 2 has to
# happen before that — and generated/ must be part of any backup for step 2 to
# survive a restore.
resolve_install_id() {
  if [[ -n "${TOPS_INSTALL_ID:-}" ]]; then
    log "Using TOPS_INSTALL_ID from the environment."
  elif TOPS_INSTALL_ID="$(env_file_value TOPS_INSTALL_ID)" && [[ -n "$TOPS_INSTALL_ID" ]]; then
    log "Reusing the existing install id from ${ENV_FILE} — onboarding links stay valid."
  else
    TOPS_INSTALL_ID="$(python3 -c 'import uuid; print(uuid.uuid4())')"
    log "Minted a new install id for this installation."
  fi

  export TOPS_INSTALL_ID

  # During a rotation, set TOPS_INSTALL_ID to the new value and
  # TOPS_INSTALL_ID_PREVIOUS to the old one. Both are accepted by the filter
  # policy until the next install drops the previous one, so onboarding that is
  # already in flight is not broken by the rotation.
  if [[ -n "${TOPS_INSTALL_ID_PREVIOUS:-}" ]]; then
    TOPS_INSTALL_IDS="${TOPS_INSTALL_ID},${TOPS_INSTALL_ID_PREVIOUS}"
    log "Rotation in progress: the previous install id is still accepted."
  else
    TOPS_INSTALL_IDS="${TOPS_INSTALL_ID}"
  fi

  export TOPS_INSTALL_IDS
}

deploy_sns() {
  local environment="${TOPS_ENVIRONMENT:-test}"
  local stack_name="${TOPS_SNS_STACK:-teemops-messaging-${environment}}"

  if [[ ! -f "$SNS_TEMPLATE" ]]; then
    die "SNS template not found: $SNS_TEMPLATE"
  fi

  log "Deploying SNS stack ${stack_name}..."
  aws cloudformation deploy \
    --template-file "$SNS_TEMPLATE" \
    --stack-name "$stack_name" \
    --region "$AWS_DEFAULT_REGION" \
    --no-fail-on-empty-changeset \
    --parameter-overrides \
      "SQSLabel=teemops_main" \
      "TopsInstallIds=${TOPS_INSTALL_IDS}" \
    2>&1 | tee -a "$LOG_FILE"

  export SNS_STACK="$stack_name"
}

upload_child_account_template() {
  local bucket_name="$1"
  local template_path="${ROOT}/templates/iam.role.child.account.cfn.yaml"
  local s3_key="templates/iam.role.child.account.cfn.yaml"

  if [[ ! -f "$template_path" ]]; then
    die "Child account template not found: $template_path"
  fi

  log "Uploading child account CloudFormation template to s3://${bucket_name}/${s3_key}..."
  aws s3 cp "$template_path" "s3://${bucket_name}/${s3_key}" \
    --region "$AWS_DEFAULT_REGION" \
    2>&1 | tee -a "$LOG_FILE"

  export TOPS_CFN_TEMPLATE_URL="https://${bucket_name}.s3.${AWS_DEFAULT_REGION}.amazonaws.com/${s3_key}"
  log "Child account template URL: ${TOPS_CFN_TEMPLATE_URL}"
}

write_env_file() {
  local environment="${TOPS_ENVIRONMENT:-test}"
  local main_name main_arn main_dlq_name main_dlq_arn audit_name audit_arn region_name region_arn bucket_name sns_arn quarantine_name quarantine_arn

  main_name="$(cf_output "$CORE_STACK" TopsMainQueueName)"
  main_arn="$(cf_output "$CORE_STACK" TopsMainQueueArn)"
  main_dlq_name="$(cf_output "$CORE_STACK" TopsMainDlqName)"
  main_dlq_arn="$(cf_output "$CORE_STACK" TopsMainDlqArn)"
  audit_name="$(cf_output "$CORE_STACK" TopsAuditQueueName)"
  audit_arn="$(cf_output "$CORE_STACK" TopsAuditQueueArn)"
  region_name="$(cf_output "$CORE_STACK" TopsAuditRegionQueueName)"
  region_arn="$(cf_output "$CORE_STACK" TopsAuditRegionQueueArn)"
  bucket_name="$(cf_output "$CORE_STACK" DeploymentBucketName)"
  sns_arn="$(cf_output "$SNS_STACK" TopicArn)"
  quarantine_name="$(cf_output "$SNS_STACK" QuarantineQueueName)"
  quarantine_arn="$(cf_output "$SNS_STACK" QuarantineQueueArn)"

  for var_name in main_name main_arn main_dlq_name main_dlq_arn audit_name audit_arn region_name region_arn bucket_name sns_arn quarantine_name quarantine_arn; do
    if [[ -z "${!var_name}" || "${!var_name}" == "None" ]]; then
      die "Missing CloudFormation output: ${var_name}"
    fi
  done

  upload_child_account_template "$bucket_name"

  cat > "$ENV_FILE" <<EOF
# Generated by Teemops installer — do not edit manually
# $(date -u +"%Y-%m-%dT%H:%M:%SZ")

AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION}
AWS_PARENT_ACCOUNT_ID=${DETECTED_AWS_ACCOUNT_ID}

TOPS_ENVIRONMENT=${environment}
TOPS_DEPLOYMENT_REGION=${AWS_DEFAULT_REGION}
TOPS_DEPLOY_BUCKET=${bucket_name}

TOPS_SQS_NAME=${main_name}
TOPS_SQS_ARN=${main_arn}
TOPS_SQS_DLQ_NAME=${main_dlq_name}
TOPS_SQS_DLQ_ARN=${main_dlq_arn}
TOPS_AUDIT_SQS_NAME=${audit_name}
TOPS_AUDIT_SQS_ARN=${audit_arn}
TOPS_AUDIT_REGION_SQS_NAME=${region_name}
TOPS_AUDIT_REGION_SQS_ARN=${region_arn}

TOPS_SNS_ARN=${sns_arn}
TOPS_CFN_TEMPLATE_URL=${TOPS_CFN_TEMPLATE_URL}

# Install-scoped filter secret (N-11). Minted once, on the first AWS install, and
# reused on every re-run — see resolve_install_id in install-messaging.sh.
#
# BACK THIS UP. It is not recoverable from AWS: if generated/ is lost and this
# value is regenerated, every onboarding link already issued stops working, and
# the Delete ping from every account already linked is silently filtered out, so
# unlinking fails with no error. To rotate deliberately, set TOPS_INSTALL_ID to
# the new value and TOPS_INSTALL_ID_PREVIOUS to this one in .env, then re-run
# ./install.sh --aws-only.
TOPS_INSTALL_ID=${TOPS_INSTALL_ID}

# Holds account-link pings the install-id filter rejected, so a wrong or missing
# id is visible instead of vanishing. Should normally be empty:
#   aws sqs get-queue-attributes --queue-name ${quarantine_name} \\
#     --attribute-names ApproximateNumberOfMessages
TOPS_QUARANTINE_SQS_NAME=${quarantine_name}
TOPS_QUARANTINE_SQS_ARN=${quarantine_arn}

# Scan processing runs on the local database queue for the single-server Docker
# deployment: the app (php-fpm) needs no AWS credentials to enqueue a scan, and
# the worker consumes these queues on the 'database' connection. SQS is used only
# for the account-linking callback (SNS -> teemops_main, polled by aws:process-sqs).
# The teemops_audit* SQS queues above stay provisioned but idle; set these to
# sqs-audit / sqs-audit-region if you ever want to move scans onto SQS.
SCAN_QUEUE_CONNECTION=database
SCAN_REGION_QUEUE_CONNECTION=database
QUEUE_CONNECTION=database
EOF

  log "Wrote ${ENV_FILE}"
  log "Restart app stack: docker compose up -d"
}

main() {
  : > "$LOG_FILE"
  load_dotenv
  require_region
  validate_aws_auth
  # Before deploy_sns (it needs the id) and before write_env_file (which rewrites
  # the file the previous id is read back from).
  resolve_install_id
  deploy_core_docker
  deploy_sns
  write_env_file
  log "Messaging install complete."
}

# Only deploy when executed. Sourcing defines the functions without running
# anything, which is how tests/install-messaging.test.sh exercises load_dotenv
# and validate_aws_auth without deploying a stack.
if [[ "${BASH_SOURCE[0]}" == "${0}" ]]; then
  main "$@"
fi
