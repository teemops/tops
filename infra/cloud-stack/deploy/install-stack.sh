#!/usr/bin/env bash
# Deploy SAM-packaged CloudFormation from the templates/ directory (this repo).
set -euo pipefail

export AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION:-ap-southeast-6}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

usage() {
  echo "Usage: $0 [core|ec2] [-- sam-deploy-args...]" >&2
  echo "  core  - template.yaml (default): SQS, SNS, IAM EC2 root, VPC" >&2
  echo "  ec2   - template-ec2.yaml: EC2+ALB nested stack (requires parameters)" >&2
  exit 1
}

if [[ "${1:-}" == "-h" ]] || [[ "${1:-}" == "--help" ]]; then
  usage
fi

TARGET="${1:-core}"
if [[ $# -gt 0 ]]; then
  shift
fi

case "$TARGET" in
  core)
    sam deploy --config-env default "$@"
    ;;
  ec2)
    sam deploy --config-env ec2 "$@"
    ;;
  *)
    usage
    ;;
esac
