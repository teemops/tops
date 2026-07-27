# Start MiniStack
```
docker compose up -d
```

# Works with any AWS tool — no config changes

```
aws --endpoint-url=http://localhost:4566 s3 mb s3://my-bucket
```

# Real database — RDS spins up actual Postgres

✓ Real Postgres container running on localhost:15432
```
aws --endpoint-url=http://localhost:4566 rds create-db-instance \
--db-instance-identifier mydb --engine postgres \
--master-username admin --master-user-password secret
```

# Real Redis via ElastiCache

✓ Real Redis container running on localhost:16379
```
$ aws --endpoint-url=http://localhost:4566 elasticache \
create-cache-cluster --cache-cluster-id my-redis --engine redis
```
