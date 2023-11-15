<?php
/**
 * version 3
 * Migrate the plugin to the new daemon version
 * Return without doing anything if the new version is already installed
 */
// remove all jMQTT old daemon crons
do {
    /** @var null|object $cron */
    $cron = cron::byClassAndFunction('jMQTT', 'daemon');
    if (is_object($cron)) $cron->remove(true);
    else break;
}
while (true);

jMQTT::logger('info', __("Suppression du démon cron précédent", __FILE__));


/**
 * version 3
 * Trigger installation of new dependancies
 * Return without doing anything if the new version is already installed
 */

//Jeedom Core Bug : the main thread will end by running the previous version dependancy_info()
// (the old one says dependancies are met and it's cached...)
// Even if we invalidate dependancies infos in cache, it's back just after
// plugin::byId('jMQTT')->dependancy_info(true);

// So best option is to remove old daemon dependancies
// ***REMOVED*** Code removed due to side effect on other plugins. Problem handled by VERSION=5 and jMQTTDaemon::start() ***REMOVED***

?>
