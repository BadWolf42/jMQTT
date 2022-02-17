#!/bin/bash
######################### INCLUSION LIB ##########################
BASEDIR=$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )
wget https://raw.githubusercontent.com/NebzHB/dependance.lib/master/dependance.lib -O $BASEDIR/dependance.lib &>/dev/null
PLUGIN=$(basename "$(realpath $BASEDIR/..)")
LANG_DEP=en
. ${BASEDIR}/dependance.lib
##################################################################

pre

INSTALL_MOSQUITTO=1
if [ ! -z $2 ] && [ $2 -eq 1 -o $2 -eq 0 ]; then
	INSTALL_MOSQUITTO=$2
fi

echo "== Should install Mosquitto:" ${INSTALL_MOSQUITTO}

step 10 "Synchronize the package index"
try sudo apt-get update

if [ ${INSTALL_MOSQUITTO} -eq 1 ]; then
	step 20 "Install Mosquitto"
	try sudo DEBIAN_FRONTEND=noninteractive apt-get install -y mosquitto

	if [ $(ls /etc/mosquitto/conf.d/*.conf 2>/dev/null | wc -w) -eq 0 ]; then
		step 30 "Configure Mosquitto"
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
try php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
try php composer-setup.php
try php -r "unlink('composer-setup.php');"

step 25 "Install Ratchet PHP library"
try sudo -u www-data php ./composer.phar update --working-dir=./Ratchet

step 30 "Temporary fix of Ratchet PHP library (https://github.com/ratchetphp/RFC6455/pull/65)"
if [ $(getconf LONG_BIT) -eq 32 ]; then
	try sed -i -E "s/unpack\('J'(.*)\)\[1\]/unpack('N2'\1)[2]/g" ./Ratchet/vendor/ratchet/rfc6455/src/Messaging/MessageBuffer.php
fi

step 35 "Install JsonPath-PHP library"
try sudo -u www-data php ./composer.phar update --working-dir=./JsonPath-PHP

step 40 "Remove Composer"
silent rm composer.phar

step 50 "Install python3 debian packages"
try sudo DEBIAN_FRONTEND=noninteractive apt-get install -y python3-requests python3-pip python3-wheel

step 60 "Install python3 setuptools library"
try pip3 install --upgrade setuptools

step 70 "Install python3 paho-mqtt library"
try pip3 install --upgrade paho-mqtt

step 80 "Install python3 websocket-client library"
try pip3 install --upgrade websocket-client

step 90 "Run post_dependancy_install function"
cd "$( dirname "${BASH_SOURCE[0]}" )"
try sudo -u www-data php -r 'include "../core/class/jMQTT.class.php"; jMQTT::post_dependancy_install();'

post
