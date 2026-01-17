#!/bin/bash
#This is to be run from root of the project
#e.g. sh ./infra/utils/deploy/deploy.sh dev
ENV=$1
#zip up api folder and store zip file in this dir
zip -r .topsbuild/app.zip app -x "app/playwright-report/*"  "app/node_modules/*" "app/.env" "app/.env.*" "app/.git/*" "app/.gitignore"
#copy zip file to s3
aws s3 cp .topsbuild/app.zip s3://$ENV-tops-deploy/.topsbuild/app.zip

ssh topsprod "sudo sh /srv/scripts/update.sh $ENV"

