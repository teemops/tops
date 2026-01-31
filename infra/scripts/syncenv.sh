#!/bin/bash
#This is to be run from local host to push env file to S3
#e.g. sh ./syncenv.sh dev
ENV=$1
#copy env file to S3
aws s3 cp $ENV/app.env s3://$ENV-tops-deploy/app/app.env
