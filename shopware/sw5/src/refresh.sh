#!/usr/bin/env bash

bin/console sw:plugin:uninstall WWFDonationPlugin
bin/console sw:cache:clear
bin/console sw:plugin:refresh
bin/console sw:plugin:list
bin/console sw:plugin:install --activate --clear-cache WWFDonationPlugin
bin/console sw:thumbnail:generate
bin/console sw:cache:clear