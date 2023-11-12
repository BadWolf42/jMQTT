<?php
/**
 * version 2
 * Migrate the plugin to the new version with no auto_add_cmd on broker
 * Return without doing anything if the new version is already installed
 */

//disable auto_add_cmd on Brokers eqpt because auto_add is removed for them
foreach ((jMQTT::getBrokers()) as $broker) {
    $broker->setAutoAddCmd('0');
    $broker->save();
}

jMQTT::logger('info', __("Désactivation de l'ajout automatique de commandes sur les Broker", __FILE__));

?>
