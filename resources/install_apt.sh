#! /bin/bash

PROGRESS_FILE=/tmp/dependancy_jmqtt
if [ ! -z $1 ]; then
    PROGRESS_FILE=$1
fi

INSTALL_MOSQUITTO=1
if [ ! -z $2 ] && [ $2 -eq 1 -o $2 -eq 0 ]; then
    INSTALL_MOSQUITTO=$2
fi

touch ${PROGRESS_FILE}
echo 0 > ${PROGRESS_FILE}

echo "********************************************************"
echo "*              dependancies Installation               *"
echo "********************************************************"
echo "> Progress file: " ${PROGRESS_FILE}
echo "> Install Mosquitto: " ${INSTALL_MOSQUITTO}

echo "*"
echo "* Synchronize the package index"
echo "*"
apt-get update
echo 20 > ${PROGRESS_FILE}

echo "*"
echo "* Install Mosquitto"
echo "*"
if [ ${INSTALL_MOSQUITTO} -eq 1 ]; then
    apt-get -y install mosquitto
fi
echo 40 > ${PROGRESS_FILE}

echo "*"
echo "* Install Ratchet PHP library"
echo "*"
cd "$( dirname "${BASH_SOURCE[0]}" )"
cd ../resources
rm -rf vendor
rm -f composer.json
rm -f composer.lock
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
sudo -u www-data php ./composer.phar require --working-dir=. --no-cache symfony/http-foundation
sudo -u www-data php ./composer.phar require --working-dir=. --no-cache cboden/ratchet
rm composer.phar
echo 50 > ${PROGRESS_FILE}

echo "*"
echo "* Install python3 debian packages"
echo "*"
apt-get install -y python3-requests python3-pip
echo 60 > ${PROGRESS_FILE}

echo "*"
echo "* Install python3 paho-mqtt library"
echo "*"
pip3 install --upgrade paho-mqtt
echo 70 > ${PROGRESS_FILE}

echo "*"
echo "* Install python3 websocket-client library"
echo "*"
pip3 install --upgrade websocket-client
echo 80 > ${PROGRESS_FILE}

echo "*"
echo "* Run post_dependancy_install function"
echo "*"
cd "$( dirname "${BASH_SOURCE[0]}" )"
sudo -u www-data php -r 'include "../core/class/jMQTT.class.php"; jMQTT::post_dependancy_install();'
echo 100 > ${PROGRESS_FILE}

echo "********************************************************"
echo "*             End dependancy installation              *"
echo "********************************************************"
rm ${PROGRESS_FILE}