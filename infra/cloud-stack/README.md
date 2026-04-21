## how to deploy the sam templates

```
cd infra/cloud-stack
./deploy/install-stack.sh
```

## how to deploy the cloudformation templates

```
cd infra/cloud-stack
./deploy/install-stack.sh
```

## or sam cli commands

cd templates/<stack>
# guided first time (creates bucket, confirms params)
sam deploy --guided --config-env default

