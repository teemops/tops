## how to deploy the sam templates

```
cd infra/cloud-stack
./deploy/install-stack.sh
```

### Docker / self-hosted messaging only (`core-docker`)

For Docker Compose installs, use the root installer instead of deploying full `core/` (which includes VPC + IAM):

```bash
# From repo root — deploys core-docker + SNS, writes generated/teemops.env
./install.sh
```

Manual SAM deploy:

```bash
cd infra/cloud-stack/core-docker
sam build && sam deploy --region "$TOPS_DEPLOYMENT_REGION"
```

Stacks:

| Path | Use case |
|------|----------|
| `core-docker/` | SQS + S3 only (Phase 2 Docker) |
| `core/` | SQS + IAM + VPC + S3 (Phase 4 EC2/ALB) |
| `stackset/sns.topic.cfn.yaml` | SNS topic (installer deploys in deployment region) |

## how to deploy the cloudformation templates

```
cd infra/cloud-stack
./deploy/install-stack.sh
```

## or sam cli commands

cd templates/<stack>
# guided first time (creates bucket, confirms params)
sam deploy --guided --config-env default

