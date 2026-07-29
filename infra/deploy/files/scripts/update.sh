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

#chown all files in /srv/apps/tops to be owned by www-data
chown -R www-data:www-data /srv/apps/tops
chown -R www-data:www-data /var/www/.npm

#composer install
cd /srv/apps/tops/app
sudo -u www-data composer install --optimize-autoloader --no-dev
sudo -u www-data composer update

#npm install
cd /srv/apps/tops/app
sudo -u www-data npm ci
#build front-end assets for production
# These variables should be loaded from /srv/apps/tops/app/.env into /etc/environment above.
# No need to redefine APP_URL, ZIGGY_URL, ASSET_URL here.
sudo -u www-data npm run build

#remove vite specific files
rm -f public/hot
php artisan optimize:clear
php artisan config:cache
php artisan config:clear

#migrate database
sudo -u www-data php artisan migrate

#kill all running node processes
pkill -f node

#restart nginx
systemctl restart nginx
#restart supervisor
supervisorctl reload
#rm -f /tmp/app.zip