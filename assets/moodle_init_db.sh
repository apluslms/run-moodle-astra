#!/bin/sh

# Install the Moodle database, i.e., create tables in the database.
# If the database has already been installed, the install_database.php
# script exits with status 1.

sleep 5 # wait for the database to start
cd /var/www/html
php admin/cli/install_database.php --agree-license --fullname='Moodle container' --shortname='moodle' \
  --adminuser='admin' --adminpass='admin' --adminemail='admin@localhost.invalid'

# Add test data to the database, i.e., a coursespace and test user accounts.
# This also imports the MOOC grader course into the coursespace.
php /usr/local/src/moodle_add_test_data.php

# Keep the container alive.
apache2-foreground

