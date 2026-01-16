#!/bin/bash
#This is to be run from /srv/scripts
#e.g. sh ./update.sh dev
ENV=$1
#copy zip file from S3
aws s3 cp s3://$ENV-tops-deploy/.topsbuild/api.zip /tmp/api.zip
#unzip file
unzip -o /tmp/api.zip -d /srv/apps/tops
#copy env file for dev down from S3
aws s3 cp s3://$ENV-tops-deploy/api/app.env /srv/apps/tops/api/.env
#load environment vars from environment and combine with above from S3
echo "PATH=$PATH" > /etc/environment
cat /srv/apps/tops/api/.env >> /etc/environment

#npm install
cd /srv/apps/tops/api
npm install

#migrate database
npx prisma migrate deploy

#kill all running node processes
pkill -f node

supervisorctl restart tops-api
supervisorctl restart tops-status
#rm -f /tmp/api.zip