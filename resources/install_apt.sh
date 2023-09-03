#!/bin/bash
######################### INCLUSION LIB ##########################
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
#wget https://raw.githubusercontent.com/NebzHB/dependance.lib/master/dependance.lib -O $BASEDIR/dependance.lib &>/dev/null
PROGRESS_FILENAME=dependancy
PLUGIN=$(basename "$(realpath $BASEDIR/..)")
LANG_DEP=en
. ${BASEDIR}/dependance.lib
##################################################################

pre
step 0 "Synchronize the package index"
try sudo apt-get update

step 10 "Purge dynamic contents"
silent rm -rf $BASEDIR/JsonPath-PHP/composer.lock
silent rm -rf $BASEDIR/JsonPath-PHP/vendor
silent rm -rf $BASEDIR/jmqttd/venv
silent rm -rf $BASEDIR/jmqttd/__pycache__

step 20 "Install Composer"
try wget 'https://getcomposer.org/installer' -O $BASEDIR/composer-setup.php
try php $BASEDIR/composer-setup.php --install-dir=$BASEDIR/
try rm -f $BASEDIR/composer-setup.php

step 30 "Install JsonPath-PHP library"
try sudo -u www-data php $BASEDIR/composer.phar update --working-dir=$BASEDIR/JsonPath-PHP

step 40 "Remove Composer"
silent rm $BASEDIR/composer.phar

step 50 "Install python3 venv and pip debian packages"
try sudo DEBIAN_FRONTEND=noninteractive apt-get install -y python3-venv python3-pip

step 60 "Create a python3 Virtual Environment"
try sudo -u www-data python3 -m venv $BASEDIR/jmqttd/venv

step 70 "Install required python3 libraries in venv"
try sudo -u www-data $BASEDIR/jmqttd/venv/bin/pip3 install --no-cache-dir -r $BASEDIR/python-requirements/requirements.txt

post
