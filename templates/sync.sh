#!/bin/bash
#make s3 bucket public
aws s3api put-bucket-acl --bucket storage.auditaws.com --acl public-read
aws s3 sync . s3://storage.auditaws.com/ --acl public-read --exclude ".git/*" --exclude "README.md" --exclude "sync.sh"

