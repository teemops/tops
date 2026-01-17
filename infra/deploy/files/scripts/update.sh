#!/bin/bash
#This is to be run from /srv/scripts
#e.g. sh ./update.sh dev
ENV=$1
#copy zip file from S3
aws s3 cp s3://$ENV-tops-deploy/.topsbuild/app.zip /tmp/app.zip
#unzip file
unzip -o /tmp/app.zip -d /srv/apps/tops
#copy env file for dev down from S3
aws s3 cp s3://$ENV-tops-deploy/app/app.env /srv/apps/tops/app/.env
#load environment vars from environment and combine with above from S3
echo "PATH=$PATH" > /etc/environment
cat /srv/apps/tops/app/.env >> /etc/environment

#npm install
cd /srv/apps/tops/app
npm install

#migrate database
npx prisma migrate deploy

#kill all running node processes
pkill -f node

supervisorctl restart tops-account-q
supervisorctl restart tops-queue
supervisorctl restart tops-scan-queue
supervisorctl restart tops-scan-region-q
#rm -f /tmp/app.zip