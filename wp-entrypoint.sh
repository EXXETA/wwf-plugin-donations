#!/bin/bash
set -euo pipefail

# NOTE: If you change this file, the wordpress container needs to be rebuilt

# run default wordpress entrypoint script

bash /usr/local/bin/docker-entrypoint.sh apache2-foreground &
# "wait" for the other script's finish. Note: the previous script does not terminate.
sleep 10

echo "Basic setup started. Starting WP Setup"

# variables
WP_PATH="/var/www/html"

# setup routine
wp --info
wp core config --path="$WP_PATH" --dbname=wordpress --dbuser=wordpress --dbpass=wordpress --dbhost=db \
  --dbprefix=dev_db_ --locale="de_DE" \
  --skip-check --force --extra-php <<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
PHP

wp core install --path="$WP_PATH" --url="http://127.0.0.1:8000" --title="WWF Plugin" --admin_user=admin --admin_password=password --admin_email="test@test.local"
# remove all plugins
wp plugin list

wp plugin deactivate --quiet akismet hello || true
wp plugin delete --quiet akismet hello || true

# install and activate woocommerce plugin
wp plugin install --activate woocommerce
wp plugin install --activate woocommerce-services
wp plugin install --activate debug-bar
wp plugin install --activate debug-bar-cron
wp plugin install --activate wp-mail-logging

# link donations plugin to wp-content/plugins
if [ ! -L /var/www/html/wp-content/plugins/donations-plugin ]; then
  echo "creating symlink dir for plugin development"
  ln -s /var/www/donations-plugin/ /var/www/html/wp-content/plugins/donations-plugin
fi
# .. and activate it
wp plugin activate donations-plugin || true
# set proper theme
wp theme install --activate shophistic-lite

# Trap Ctrl-c
trap terminate INT

function terminate() {
  echo "Exiting now."
  exit 0
}
echo "Running forever (press Ctrl-C to leave) ..."
while(true); do
  sleep 5
done