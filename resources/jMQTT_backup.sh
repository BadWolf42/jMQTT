#!/bin/bash
###################################################################################################
# jMQTT_backup.sh
#
#
# TODO (important) Translate this in PHP and integrate it into jMQTT_backup.php (SH not needed)
#
#

# MUST be executed as www-data with sudo right
[ $(whoami) != "www-data" ] && echo "This script MUST run as www-data user, aborting!" && exit 2

RESOURCESDIR=$(cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd)
JMQTTDIR=$(realpath $RESOURCESDIR/..)
PLUGINSDIR=$(realpath $JMQTTDIR/..)
LOGDIR=$(realpath $PLUGINSDIR/../log)

# Backup start
echo "Starting jMQTT backup..."

echo -n "Cleaning up leftovers..."
sudo rm -rf $PLUGINSDIR/jMQTT_backup_running.tgz $JMQTTDIR/backup.tgz
sudo rm -rf $JMQTTDIR/backup.*.json $JMQTTDIR/mosquitto $JMQTTDIR/logs
echo "         [ OK ]"

# Call jMQTT_backup.php to backup data from inside Jeedom
php $RESOURCESDIR/jMQTT_backup.php --all

# If no jMQTT/backup.*.json -> EXIT 1
[ ! -f "$JMQTTDIR/backup.meta.json" ] && echo "Could not find backup.meta.json, aborting!" && exit 1
[ ! -f "$JMQTTDIR/backup.ids.json"  ] && echo "Could not find backup.ids.json, aborting!"  && exit 1
[ ! -f "$JMQTTDIR/backup.data.json" ] && echo "Could not find backup.data.json, aborting!" && exit 1
[ ! -f "$JMQTTDIR/backup.hist.json" ] && echo "Could not find backup.hist.json, aborting!" && exit 1

# Backup logs
echo -n "Backing up jMQTT log files..."
sudo mkdir $JMQTTDIR/logs
sudo cp -a $LOGDIR/jMQTT* $JMQTTDIR/logs
echo "    [ OK ]"

# Backup mosquitto config
echo -n "Backing up Mosquitto config..."
sudo cp -a /etc/mosquitto $JMQTTDIR # do not put trailing '/'
echo "   [ OK ]"

# Go to plugins folder
cd $PLUGINSDIR

# Build archive
echo -n "Creating archive..."
sudo tar -zcf $PLUGINSDIR/jMQTT_backup_running.tgz jMQTT/
sudo chown www-data:www-data $PLUGINSDIR/jMQTT_backup_running.tgz
mv $PLUGINSDIR/jMQTT_backup_running.tgz $JMQTTDIR/backup.tgz
echo "              [ OK ]"

# Cleanup
echo -n "Cleaning up leftovers..."
sudo rm -rf $JMQTTDIR/backup.*.json $JMQTTDIR/mosquitto $JMQTTDIR/logs
echo "         [ OK ]"

echo "Done!"
exit 0
