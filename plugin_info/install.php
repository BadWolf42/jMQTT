<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once __DIR__ . '/../../../core/php/core.inc.php';
include_file('core', 'jMQTT', 'class', 'jMQTT');

/**
 * jMQTT plugin version configuration parameter key name
 */
define("VERSION", 'version');

/**
 * Current Update version
 */
define("CURRENT_VERSION", 2);


/**
 * No version number
 * Migrate the plugin to the multi broker version
 * Return without doing anything if the multi broker version is already installed
 */
function migrateToMultiBrokerVersion() {
    
    // Return if the multi broker version is already installed
    $res = config::searchKey('mqttId', 'jMQTT');
    if (empty($res)) {
        log::add('jMQTT', 'info', 'multi-broker version is already installed');
        return;
    }
            
    // Try to identify which equipment can be converted to the broker
    // Should be the one containing the status command
    $mqttId = config::byKey('mqttId', 'jMQTT', 'jeedom');
    $topic = $mqttId . '/' . jMQTT::CLIENT_STATUS;
    $cmds = cmd::byLogicalId($topic, 'info');
    if (count($cmds) == 0) {
        $broker = jMQTT::createEquipment(null, $mqttId, $mqttId . '/#', jMQTT::TYP_BRK);
        $msg = 'créé';
    }
    else {
        $broker = $cmds[0]->getEqLogic();
        if (count($cmds) > 1) {
            message::add('jMQTT', 'Plusieurs commandes ayant pour topic "' . $topic . '" existent. ' .
                "Considère celle avec l'id=" . $broker->getId() . ' pour choisir le broker.');
        }
        $msg = 'sélectionné';
    }
    
    $msg = "L'équipement " . $broker->getName() . " a été " . $msg. " comme broker MQTT.";
    message::add('jMQTT', $msg);
    log::add('jMQTT', 'debug', $msg);
    
    // Transfer plugin parameters
    $conf_params = array(
        'mqttAdress' => array('new_key' => 'mqttAddress', 'def' => 'localhost'),
        'mqttPort' => '1883', 'mqttId' => 'jeedom', 'mqttUser' => '',
        'mqttPass' => '', 'mqttTopic' => array('new_key' => 'mqttIncTopic', 'def' => '#'),
        'api' => jMQTT::API_DISABLE);
    foreach ($conf_params as $key => $p) {
        if (is_array($p)) {
            $new_key = $p['new_key'];
            $def = $p['def'];
        }
        else {
            $new_key = $key;
            $def = $p;
        }
        $broker->setConfiguration($new_key, config::byKey($key, 'jMQTT', $def));
        config::remove($key, 'jMQTT');
    }

    // Suppress no more used parameters
    config::remove('include_mode', 'jMQTT');
    config::remove('status', 'jMQTT');
    
    $broker->setType(jMQTT::TYP_BRK);
    $broker->setBrkId($broker->getId());
    $broker->save(true);
    
    // Create the MQTT status information command
    $broker->createMqttClientStatusCmd();
    
    foreach (eqLogic::byType('jMQTT') as $eqL) {
        /** @var jMQTT $eqL */
        try {
            log::add('jMQTT', 'debug', 'export before of ' . $eqL->getName());
            log::add('jMQTT', 'debug', json_encode($eqL->full_export()));

            if ($eqL->getType() == '') {
                $eqL->setType(jMQTT::TYP_EQPT);
                $eqL->setBrkId($broker->getId());
            }
            $eqL->cleanEquipment();
            $eqL->save(true);

            log::add('jMQTT', 'debug', 'export after of ' . $eqL->getName());
            log::add('jMQTT', 'debug', json_encode($eqL->full_export()));
        }
        catch (Exception $e) {
            log::add('jMQTT', 'debug', 'catch exception ' . $e->getMessage());
            $s = print_r(str_replace('/var/www/html', '', $e->getTraceAsString()), true);
            log::add('jMQTT', 'debug', $s);
        }
    }

    log::add('jMQTT', 'info', 'migration to multi-broker version done');
}

/**
 * version 1
 * Migrate the plugin to the new JSON version (implementing #76)
 * Return without doing anything if the new JSON version is already installed
 */
function migrateToJsonVersion() {
    $version = config::byKey(VERSION, 'jMQTT', 0);
    if ($version >= 1) {
        return;
    }
    
    /** @var cmd $cmd */
    foreach (cmd::searchConfiguration('', 'jMQTT') as $cmd) {
        log::add('jMQTT', 'debug', 'migrate info command ' . $cmd->getName());
        $cmd->setConfiguration('parseJson', null);
        $cmd->setConfiguration('prevParseJson', null);
        $cmd->setConfiguration('jParent', null);
        $cmd->setConfiguration('jOrder', null);
        $cmd->save();
    }
    
    log::add('jMQTT', 'info', 'migration to json#76 version done');
}

/**
 * version 2
 * Migrate the plugin to the new version with no auto_add_cmd on broker
 * Return without doing anything if the new version is already installed
 */
function disableAutoAddCmdOnBrokers() {
    $version = config::byKey(VERSION, 'jMQTT', 0);
    if ($version >= 2) {
        return;
    }
    
    //disable auto_add_cmd on Brokers eqpt because auto_add is removed for them
    foreach ((jMQTT::getBrokers()) as $broker) {
        $broker->setAutoAddCmd('0');
    }
    
    log::add('jMQTT', 'info', 'migration to no auto_add_cmd for broker done');
}

function jMQTT_install() {
    jMQTT_update();
}

function jMQTT_update() {
    // Stop the plugin
    jMQTT::stop();
    
    migrateToMultiBrokerVersion();
    migrateToJsonVersion();
    disableAutoAddCmdOnBrokers();

    // Update version next to upgrade operations
    config::save(VERSION, CURRENT_VERSION, 'jMQTT');

    // force the refresh of the dependancy info
    // otherwise the cache value is kept
    plugin::byId('jMQTT')->dependancy_info(true);
    
    // Start the plugin
    jMQTT::start();
}

function jMQTT_remove() {
    do {
        $cron = cron::byClassAndFunction('jMQTT', 'daemon');
        if (is_object($cron))
            $cron->remove(true);
            else
                break;
    }
    while (true);
}

?>
