#! /bin/bash

PROGRESS_FILE=/tmp/jmqtt_dep;
if [ ! -z $1 ]; then
    PROGRESS_FILE=$1
fi
touch ${PROGRESS_FILE}


echo "********************************************************"
echo "* Install dependancies                                 *"
echo "********************************************************"

echo "*"
echo "* Update package source repository"
echo "*"
echo 0 > ${PROGRESS_FILE}
apt-get -y install lsb-release php-pear
archi=`lscpu | grep Architecture | awk '{ print $2 }'`
echo 10 > ${PROGRESS_FILE}


if [ "$archi" == "x86_64" ]; then
    cd /tmp
    if [ `lsb_release -i -s` == "Debian" ]; then
	wget http://repo.mosquitto.org/debian/mosquitto-repo.gpg.key
	apt-key add mosquitto-repo.gpg.key
	rm mosquitto-repo.gpg.key
	if [ `lsb_release -c -s` == "jessie" ]; then
	    wget http://repo.mosquitto.org/debian/mosquitto-jessie.list
	    mv -f mosquitto-jessie.list /etc/apt/sources.list.d/mosquitto-jessie.list
	fi
	if [ `lsb_release -c -s` == "stretch" ]; then
	    wget http://repo.mosquitto.org/debian/mosquitto-stretch.list
	    mv -f mosquitto-stretch.list /etc/apt/sources.list.d/mosquitto-stretch.list
	fi
    fi
fi
echo 20 > ${PROGRESS_FILE}

echo "*"
echo "* Synchronize the package index"
echo "*"
apt-get update
echo 40 > ${PROGRESS_FILE}

echo "*"
echo "* Install Mosquitto"
echo "*"
apt-get -y install mosquitto mosquitto-clients libmosquitto-dev
echo 60 > ${PROGRESS_FILE}

echo "*"
echo "* Install php mosquitto wrapper"
echo "*"
if [[ -d "/etc/php5/" ]]; then
    apt-get -y install php5-dev
    echo 80 > ${PROGRESS_FILE}
    if [[ -d "/etc/php5/cli/" && ! `cat /etc/php5/cli/php.ini | grep "mosquitto"` ]]; then
  	echo "" | pecl install Mosquitto-alpha
  	echo "extension=mosquitto.so" | tee -a /etc/php5/cli/php.ini
    fi
    if [[ -d "/etc/php5/fpm/" && ! `cat /etc/php5/fpm/php.ini | grep "mosquitto"` ]]; then
  	echo "extension=mosquitto.so" | tee -a /etc/php5/fpm/php.ini
	service php5-fpm reload
    fi
    if [[ -d "/etc/php5/apache2/" && ! `cat /etc/php5/apache2/php.ini | grep "mosquitto"` ]]; then
	echo "extension=mosquitto.so" | tee -a /etc/php5/apache2/php.ini
	service apache2 reload
    fi
else
    apt-get -y install php7.0-dev
    echo 80 > ${PROGRESS_FILE}
    if [[ -d "/etc/php/7.0/cli/" && ! `cat /etc/php/7.0/cli/php.ini | grep "mosquitto"` ]]; then
	echo "" | pecl install Mosquitto-alpha
	echo "extension=mosquitto.so" | tee -a /etc/php/7.0/cli/php.ini
    fi
    if [[ -d "/etc/php/7.0/fpm/" && ! `cat /etc/php/7.0/fpm/php.ini | grep "mosquitto"` ]]; then
	echo "extension=mosquitto.so" | tee -a /etc/php/7.0/fpm/php.ini
	service php5-fpm reload
    fi
    if [[ -d "/etc/php/7.0/apache2/" && ! `cat /etc/php/7.0/apache2/php.ini | grep "mosquitto"` ]]; then
	echo "extension=mosquitto.so" | tee -a /etc/php/7.0/apache2/php.ini
	service apache2 reload
    fi
fi

rm ${PROGRESS_FILE}

echo "********************************************************"
echo "*             End dependancy installation              *"
echo "********************************************************"
