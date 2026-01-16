#!/bin/bash
#This is to be run from root of the project
#e.g. sh ./infra/utils/deploy/setup.sh prod
ENV=$1
scp -r infra/utils/deploy/files/ tops$ENV:/home/ubuntu/
ssh tops$ENV "sudo bash /home/ubuntu/files/init/tops"
