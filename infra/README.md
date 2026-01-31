# Infra

## Steps

Deploy environment variables from local to prod/test environment in S3

```
cd infra/scripts
chmod +x syncenv.sh && ./syncenv.sh prod
```

Setup EC2 instance with OS packages required for PHP Laravel

```
#in root dir
./infra/deploy/setup.sh prod
```

Deploy latest code to EC2 

```
#in root dir
./infra/deploy/deploy.sh prod
```
