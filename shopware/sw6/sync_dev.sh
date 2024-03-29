#!/usr/bin/env bash
# the dockware container has to be started for this script to work!
set -e

echo "Sync this repository with dockware container and (re-)build afterwards..."
date +%c

dir=$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
cd "$dir"
pwd

# download shopware-directories to local directory
docker exec shopware bash -c 'sudo chown -R www-data:www-data /var/www/html'
docker cp shopware:/var/www/html/vendor ./src/ || true
docker cp shopware:/var/www/html/bin ./src/
docker cp shopware:/var/www/html/var ./src/
docker cp shopware:/var/www/html/config ./src/
docker cp shopware:/var/www/html/public ./src/
docker cp shopware:/var/www/html/src ./src/
docker cp shopware:/var/www/html/files ./src/

docker cp shopware:/var/www/html/.env ./src/.env
docker cp shopware:/var/www/html/install.lock ./src/install.lock
docker cp shopware:/var/www/html/PLATFORM_COMMIT_SHA ./src/PLATFORM_COMMIT_SHA

docker cp shopware:/var/www/html/composer.json ./src/composer.json
docker cp shopware:/var/www/html/composer.lock ./src/composer.lock

# copy local plugin code to the container
docker exec shopware bash -c 'rm -rf /var/www/html/custom/plugins/DockwareSamplePlugin'
docker cp ./src/custom/plugins shopware:/var/www/html/custom
# Set proper permissions
docker exec shopware bash -c 'sudo chown -R www-data:www-data /var/www/html'
docker exec shopware bash -c 'cd /var/www/html; bin/console cache:clear; bin/console plugin:refresh; bin/console plugin:install --activate WWFDonationPlugin; bin/console cache:clear'

# build everything
echo "Starting shopware 6 build process..."
docker exec shopware bash -c "bash /var/www/html/bin/build-administration.sh"
docker exec shopware bash -c "bash /var/www/html/bin/build-storefront.sh"

date +%c
echo "OK."
