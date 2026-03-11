<?php

// for each brokers
foreach ((jMQTT::getBrokers()) as $broker) {
    // remove log level
    $broker->setConfiguration('loglevel', null);
    $broker->save();

    // remove log from global Jeedom config
    $old_log = 'jMQTT_' . str_replace(' ', '_', $broker->getName());
    config::remove('log::level::' . $old_log, 'jMQTT');

    // remove old log file
    unlink(log::getPathToLog($old_log));
}

jMQTT::logger('info', "Log levels removed from Brokers");

?>
