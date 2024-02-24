#!/usr/bin/env bash

# This file is configured in .lando.yml. Run: lando logs-drupal.
# It will enable the syslog module using config_split module,
# start the rsyslog service and tail the log file. This is really
# only necessary because Drush 10 removed the ability to tail `drush wd-show`.

NORMAL="\033[0m"
YELLOW="\033[32m"

echo
echo -e "${YELLOW}Tailing logs...${NORMAL}"
echo

tail -f /lando/logs/drupal.log | tr -s '|' \\t