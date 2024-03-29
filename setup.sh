#!/usr/bin/env bash

# script to set up a development environment for all shop plugins contained in this project
echo "setting up development environment"

# check for required available commands of this script
which npm &>/dev/null
[ $? -eq 0 ] || echo "npm command not found."
which php &>/dev/null
[ $? -eq 0 ] || echo "php command not found."
which mkdir &>/dev/null
[ $? -eq 0 ] || echo "mkdir command not found."
which curl &>/dev/null
[ $? -eq 0 ] || echo "curl command not found."

set -eu

dir=$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
cd "$dir"

# download composer
if [ ! -f composer.phar ]; then
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"  
  # atm only composer 1 is supported
  php composer-setup.php --1
  mv composer.phar composer1.phar
  php composer-setup.php --2
  php -r "unlink('composer-setup.php');"
fi

# build banner core package for php composer
cd core
php ../composer.phar install
cd -

# assemble core assets
cd assets
npm i
npm run assemble
cd -

# shop-specific setup instructions follow here:
cd wp
# download wp-cli
if [ ! -f wp-cli.phar ]; then
  curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
php wp-cli.phar --info

# download current wordpress
mkdir -p wp
cd wp
php ../wp-cli.phar core download || true
cd "$dir/wp"

# setup development environment
cd ./wwf-donations-plugin
php ../../composer1.phar install || php ../../composer1.phar dump-autoload || true

npm i
npm run build-js
npm run build:clean

cd "$dir"
bash ./shopware/setup.sh

echo "Setup OK."
