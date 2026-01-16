#!/bin/bash
cd /srv/apps/tops/api
#source /srv/apps/tops/api/.env
#export all the variavbles from above file into the environment
export $(cat /srv/apps/tops/api/.env | xargs)
npm run status-prod