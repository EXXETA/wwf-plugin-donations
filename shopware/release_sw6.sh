#!/usr/bin/env bash

set -eu

dir=$(cd -P -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)
cd "$dir"

cd sw6/test

# just to be sure its down
docker-compose down || true
cd ..

docker-compose down || true
docker-compose pull
docker-compose up -d

sleep 10 # give the container some time to startup (the db etc.)

cd "$dir"

rm -rf ../release/sw6 || true
mkdir -p ../release/sw6

cd ./sw6/src/custom/plugins/WWFDonationPlugin/
rm -rf node_modules || true
rm -rf vendor || true

npm install
npm run clean
npm run build
php ../../../../../../composer.phar install --no-dev

cd "$dir/sw6/src/custom/plugins/WWFDonationPlugin"
bash assemble.sh

cd "$dir/sw6"
bash ./sync_release.sh

cd "$dir"

echo -e "\n\nBuild process finished. Retrieving release files..."
docker cp shopware:/var/www/html/custom/plugins/WWFDonationPlugin sw6/src/custom/plugins

cd "$dir"
cp -fr sw6/src/custom/plugins/WWFDonationPlugin ../release/sw6
cd ../release/sw6/WWFDonationPlugin

# cleanup release directory
rm -rf src/Resources/app/administration/static
rm .gitignore
rm assemble.sh
rm phpstan.neon
rm phpunit.xml
rm -rf node_modules
rm -rf tests
rm package.json
rm package-lock.json

# Copy over LICENSE file
cp ../../../LICENSE .
cp ../../../shopware/README.md .

cd ..

if [ -x "$(command -v zip)" ]; then
  zip -r sw6-wwf-donations-plugin.zip WWFDonationPlugin
  if [ -x "$(command -v du)" ]; then
    du -d0 -h sw6-wwf-donations-plugin.zip
  fi
fi

cd "$dir/sw6"
docker-compose down || true

echo "SW6 OK."
