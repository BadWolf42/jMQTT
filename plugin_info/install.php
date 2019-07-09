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
 * Migrate the plugin to the multi broker version
 * Return without doing anything the multi broker version is already installed
 */
function migrateToMultiBrokerVersion() {
    
    // Return if the multi broker version is already installed
    $res = config::searchKey('mqttId', 'jMQTT');
    if (empty($res)) {
        log::add('jMQTT', 'debug', 'multi-broker version is already installed');
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
        $eqL = null;
        try {
            log::add('jMQTT', 'debug', 'export before of ' . $eqL->getName());
            $s = print_r($eqL->full_export(), true);
            log::add('jMQTT', 'debug', $s);

            $eqL->setConfiguration('prev_Qos', null);
            $eqL->setConfiguration('prev_isActive', null);
            $eqL->setConfiguration('reload_d', null);
            $eqL->setConfiguration('topic', null);
            if ($eqL->getType() == '') {
                $eqL->setType(jMQTT::TYP_EQPT);
                $eqL->setBrkId($broker->getId());
            }
            $eqL->save(true);

            log::add('jMQTT', 'debug', 'export after of ' . $eqL->getName());
            $s = print_r($eqL->full_export(), true);
            log::add('jMQTT', 'debug', $s);
        }
        catch (Exception $e) {
            log::add('jMQTT', 'debug', 'catch exception ' . $e->getMessage());
            $s = print_r(str_replace('/var/www/html', '', $e->getTraceAsString()), true);
            log::add('jMQTT', 'debug', $s);
        }
    }
}

function jMQTT_install() {
    jMQTT_update();
}

function jMQTT_update() {
    migrateToMultiBrokerVersion();
    
    // force the refresh of the dependancy info
    // otherwise the cache value is kept
    plugin::byId('jMQTT')->dependancy_info(true);
    
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
