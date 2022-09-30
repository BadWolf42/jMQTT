#!/bin/bash
######################### INCLUSION LIB ##########################
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
wget https://raw.githubusercontent.com/NebzHB/dependance.lib/master/dependance.lib -O $BASEDIR/dependance.lib &>/dev/null
PROGRESS_FILENAME=dependancy
PLUGIN=$(basename "$(realpath $BASEDIR/..)")
LANG_DEP=en
. ${BASEDIR}/dependance.lib
##################################################################

pre
step 0 "Checking parameters"

INSTALL_MOSQUITTO=1
if [ -n $2 ] && [ $2 -eq 1 -o $2 -eq 0 ]; then
	INSTALL_MOSQUITTO=$2
fi

LOCAL_VERSION="????"
if [ -n $3 ]; then
	LOCAL_VERSION=$3
fi

echo "== System: "`uname -a`
echo "== Jeedom version: "`cat ${BASEDIR}/../../../core/config/version`
echo "== jMQTT version: "${LOCAL_VERSION}
echo "== Install Mosquitto:" ${INSTALL_MOSQUITTO}

step 5 "Synchronize the package index"
try sudo apt-get update

if [ ${INSTALL_MOSQUITTO} -eq 1 ]; then
	step 10 "Install Mosquitto"
	try sudo DEBIAN_FRONTEND=noninteractive apt-get install -y mosquitto

	if [ $(ls /etc/mosquitto/conf.d/*.conf 2>/dev/null | wc -w) -eq 0 ]; then
		step 15 "Configure Mosquitto"
		#echo "== No *.conf file found in conf.d folder"
		#echo "== Create jMQTT Mosquitto configuration file"
		echo -e "# jMQTT Mosquitto configuration file\nlistener 1883\nallow_anonymous true" > /etc/mosquitto/conf.d/jMQTT.conf
		#echo "== restart Mosquitto service"
		try service mosquitto restart
	fi
fi

step 20 "Install Composer"
cd "$( dirname "${BASH_SOURCE[0]}" )"
cd ../resources
try wget 'https://getcomposer.org/installer' -O composer-setup.php
try php composer-setup.php
try rm -f composer-setup.php

step 30 "Install JsonPath-PHP library"
try sudo -u www-data php ./composer.phar update --working-dir=./JsonPath-PHP

step 40 "Remove Composer"
silent rm composer.phar

step 50 "Install python3 venv and pip debian packages"
try sudo DEBIAN_FRONTEND=noninteractive apt-get install -y python3-venv python3-pip

step 60 "Create a python3 Virtual Environment"
try sudo -u www-data python3 -m venv $BASEDIR/jmqttd/venv

step 70 "Install required python3 libraries in venv"
try sudo -u www-data $BASEDIR/jmqttd/venv/bin/pip3 install --no-cache-dir -r $BASEDIR/python-requirements/requirements.txt

step 90 "Run post_dependancy_install function"
cd "$( dirname "${BASH_SOURCE[0]}" )"
try sudo -u www-data php -r 'include "../core/class/jMQTT.class.php"; jMQTT::post_dependancy_install();'

post
