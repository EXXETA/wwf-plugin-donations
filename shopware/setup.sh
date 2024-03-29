#!/usr/bin/env bash

set -eu

dir=$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
cd "$dir"
cd ./sw5/src/
mkdir -p engine/Shopware
mkdir -p engine/Library
mkdir -p engine/Library/Smarty
php ./../../../composer1.phar install --no-scripts

# shopware 5 stuff
cd "$dir"
cd ./sw5/src/custom/plugins/WWFDonationPlugin
php ./../../../../../../composer1.phar install
bash assemble.sh

cd "$dir"
# shopware 6 stuff
cd ./sw6/src/
mkdir -p custom/static-plugins
php ./../../../composer.phar clearcache
php ./../../../composer.phar install --no-scripts

cd "$dir"
cd ./sw6/src/custom/plugins/WWFDonationPlugin
php ./../../../../../../composer.phar clearcache
php ./../../../../../../composer.phar install --no-dev
npm i
bash assemble.sh

echo "Shopware 5 + 6 setup finished."