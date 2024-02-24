#!/usr/bin/env bash

# This file is configured in .lando.yml. Run: lando logs-drupal.
# It will enable the syslog module using config_split module,
# start the rsyslog service and tail the log file. This is really
# only necessary because Drush 10 removed the ability to tail `drush wd-show`.

NORMAL="\033[0m"
YELLOW="\033[32m"

if sudo -v 2>&1 | grep -q "may not run sudo"; then
echo
  echo
  echo -e "${YELLOW}No sudoers entry. Not stopping services.${NORMAL}"
  echo
else
  echo -e "${YELLOW}Stopping services...${NORMAL}"
  echo

  sudo service clamav-daemon stop;
  sudo service rsyslog stop;
fi