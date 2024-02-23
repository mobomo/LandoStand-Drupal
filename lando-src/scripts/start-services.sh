#!/usr/bin/env bash

# This file is configured in .lando.yml. Run: lando logs-drupal.
# It will enable the syslog module using config_split module,
# start the rsyslog service and tail the log file. This is really
# only necessary because Drush 10 removed the ability to tail `drush wd-show`.

NORMAL="\033[0m"
YELLOW="\033[32m"

if sudo -v 2>&1 | grep -q "may not run sudo"; then
  echo
  echo -e "${YELLOW}No sudoers entry. Not restarting services.${NORMAL}"
  echo
else
  echo
  echo -e "${YELLOW}Restarting services...${NORMAL}"
  echo

  sudo service clamav-daemon restart;
  sudo service rsyslog restart;
fi