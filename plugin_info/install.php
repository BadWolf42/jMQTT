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

define('VERSION_MULTI_BROKER', 'multi_broker');

/**
 * Migrate the plugin to the multi broker version
 */
function migrateToMultiBrokerVersion() {
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
    
    message::add('jMQTT', "L'équipement " . $broker->getName() . " a été " . $msg. " comme broker MQTT.");
    
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
        config::save($key, null, 'jMQTT');
    }
    
    $broker->setType(jMQTT::TYP_BRK);
    $broker->setBrkId($broker->getId());
    $broker->save();
    
    // Create the MQTT status information command
    $broker->createMqttClientStatusCmd();
    
    foreach (eqLogic::byType('jMQTT') as $eqL) {
        /** @var jMQTT $eqL */
        $eqL->setConfiguration('prev_Qos', null);
        $eqL->setConfiguration('prev_isActive', null);
        $eqL->setConfiguration('reload_d', null);
        if ($eqL->getType() == '') {
            $eqL->setType(jMQTT::TYP_EQPT);
            $eqL->setBrkId($broker->getId());
        }
        $eqL->save();
    }
    
    // Save the major version of this plugin, to be able to know which version is installed
    config::save('version', VERSION_MULTI_BROKER, 'jMQTT');
}

function jMQTT_install() {
    // multi broker support is not already available => run the migration
    if (config::byKey('version', 'jMQTT') == '' && count(jMQTT::getBrokers()) == 0) {
        migrateToMultiBrokerVersion();
    }
    // Start daemons
    jMQTT::checkAllDaemons();
}

function jMQTT_update() {
    // multi broker support is not already available => run the migration
    if (config::byKey('version', 'jMQTT') == '' && count(jMQTT::getBrokers()) == 0) {
        migrateToMultiBrokerVersion();
    }
    // Start daemons
    jMQTT::checkAllDaemons();
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
