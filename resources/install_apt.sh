#! /bin/bash

PROGRESS_FILE=/tmp/jmqtt_dep;
if [ ! -z $1 ]; then
    PROGRESS_FILE=$1
fi

INSTALL_MOSQUITTO=1
if [ ! -z $2 ] && [ $2 -eq 1 -o $2 -eq 0 ]; then
    INSTALL_MOSQUITTO=$2
fi

echo 0 > ${PROGRESS_FILE}

echo "********************************************************"
echo "* Install dependancies                                 *"
echo "********************************************************"
echo "> Progress file: " ${PROGRESS_FILE}
echo "> Install Mosquitto: " ${INSTALL_MOSQUITTO}
echo "*"
echo "* Update package source repository"
echo "*"
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
if [ ${INSTALL_MOSQUITTO} -eq 1 ]; then
    apt-get -y install mosquitto mosquitto-clients libmosquitto-dev
else
    apt-get -y install mosquitto-clients libmosquitto-dev
fi
echo 60 > ${PROGRESS_FILE}

echo "*"
echo "* Install php mosquitto wrapper"
echo "*"
php_ver=`php -version`
php_ver=${php_ver:4:1}

if [ ${php_ver} = 5 ]; then
	echo "> Version 5 of PHP detected"
	PHP_DEV_LIB="php5-dev"
	PHP_CLI_DIR="/etc/php5/cli/"
	PHP_FPM_DIR="/etc/php5/fpm/"
	PHP_APACHE_DIR="/etc/php5/apache2/"
	FPM_SERVER="php5-fpm"
	APACHE_SERVER="apache2"
elif [ ${php_ver} = 7 ]; then
	echo "> Version 7 of PHP detected"
	PHP_DEV_LIB="php7.0-dev"
	PHP_CLI_DIR="/etc/php/7.0/cli/"
	PHP_FPM_DIR="/etc/php/7.0/fpm/"
	PHP_APACHE_DIR="/etc/php/7.0/apache2/"
	FPM_SERVER="php7-fpm"
	APACHE_SERVER="apache2"
else
	PHP_DEV_LIB=""
	echo "> ERROR: no version of PHP detected"
fi

if [ -n PHP_DEV_LIB ]; then	
	echo "> Install ${PHP_DEV_LIB}"
	apt-get -y install ${PHP_DEV_LIB}
    echo 80 > ${PROGRESS_FILE}
    
    echo "> Install pecl/Mosquitto"
    echo "" | pecl install Mosquitto-alpha
    if [ $? -eq 0 ]; then
    	RELOAD="tbd"
    else
    	RELOAD=""
    fi
    echo 90 > ${PROGRESS_FILE}
    
    if [ -d ${PHP_CLI_DIR} ] && [ -e ${PHP_CLI_DIR}php.ini ] && [ ! `cat ${PHP_CLI_DIR}php.ini | grep "mosquitto"` ]; then
        echo "> Adding mosquitto.so to ${PHP_CLI_DIR}php.ini"
  		echo "extension=mosquitto.so" | tee -a ${PHP_CLI_DIR}php.ini
    fi
    
	if [ -d ${PHP_FPM_DIR} ]; then
    	if [ -n "$RELOAD" ]; then
    		RELOAD=${FPM_SERVER}
    	fi
    	if [ ! `cat ${PHP_FPM_DIR}php.ini | grep "mosquitto"` ]; then
    		echo "> Adding mosquitto.so to ${PHP_FPM_DIR}php.ini"
		  	echo "extension=mosquitto.so" | tee -a ${PHP_FPM_DIR}php.ini
		  	RELOAD=${FPM_SERVER}
		fi
    fi
    
    if [ -d ${PHP_APACHE_DIR} ]; then
    	if [ -n "$RELOAD" ]; then
    		RELOAD=${APACHE_SERVER}
    	fi
    	if [ ! `cat ${PHP_APACHE_DIR}php.ini | grep "mosquitto"` ]; then
    		echo "> Adding mosquitto.so to ${PHP_APACHE_DIR}php.ini"
			echo "extension=mosquitto.so" | tee -a ${PHP_APACHE_DIR}php.ini
			RELOAD=${APACHE_SERVER}
		fi
    fi
      
    if [ -n "${RELOAD}" ]; then
    	echo "> Reload the web server" $RELOAD
		service $RELOAD reload
	else
		echo "> No need to reload the web server"
	fi
fi

rm ${PROGRESS_FILE}

echo "********************************************************"
echo "*             End dependancy installation              *"
echo "********************************************************"
