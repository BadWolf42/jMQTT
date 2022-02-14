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
define("CURRENT_VERSION", 6);

/**
 * Force dependancy Install Flag handled by deamon_start (value = 1)
 */
define("FORCE_DEPENDANCY_INSTALL", 'forceDepInstall');


/**
 * version 0
 * Migrate the plugin to the multi broker version
 * Return without doing anything if the multi broker version is already installed
 */
function migrateToMultiBrokerVersion() {
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 0) {
		return;
	}

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
		// $broker = jMQTT::createEquipment(null, $mqttId, $mqttId . '/#', jMQTT::TYP_BRK);
		$eqpt = new jMQTT();
		$eqpt->setType(jMQTT::TYP_BRK);
		$eqpt->setName($mqttId);
		$eqpt->setIsEnable(1);
		$eqpt->save();

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
	config::remove(jMQTT::CACHE_INCLUDE_MODE, 'jMQTT');
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
	$version = config::byKey(VERSION, 'jMQTT', -1);
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
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 2) {
		return;
	}
	
	//disable auto_add_cmd on Brokers eqpt because auto_add is removed for them
	foreach ((jMQTT::getBrokers()) as $broker) {
		$broker->setAutoAddCmd('0');
		$broker->save();
	}
	
	log::add('jMQTT', 'info', 'migration to no auto_add_cmd for broker done');
}

/**
 * version 3
 * Migrate the plugin to the new daemon version
 * Return without doing anything if the new version is already installed
 */
function removePreviousDaemonCrons() {
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 3) {
		return;
	}
	
	// remove all jMQTT old daemon crons
	do {
		$cron = cron::byClassAndFunction('jMQTT', 'daemon');
		if (is_object($cron)) $cron->remove(true);
		else break;
	}
	while (true);

	log::add('jMQTT', 'info', 'removal of previous daemon cron done');
}

/**
 * version 3
 * Trigger installation of new dependancies
 * Return without doing anything if the new version is already installed
 */
function installNewDependancies() {
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 3) {
		return;
	}
	
	//Jeedom Core Bug : the main thread will end by running the previous version dependancy_info()
	// (the old one says dependancies are met and it's cached...)
	// Even if we invalidate dependancies infos in cache, it's back just after
	// plugin::byId('jMQTT')->dependancy_info(true);

	// So best option is to remove old daemon dependancies
	// ***REMOVED*** Code removed due to side effect on other plugins. Problem handled by VERSION=5 and deamon_start() ***REMOVED***
}

function tagBrokersStatusCmd() {
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 4) {
		return;
	}

	// for each brokers
	foreach ((jMQTT::getBrokers()) as $broker) {
		// for each cmd of this broker
		foreach (jMQTTCmd::byEqLogicId($broker->getId()) as $cmd) {
			// if name is 'status'
			if ($cmd->getName() == jMQTT::CLIENT_STATUS) {
				//set logicalId to status (new method to manage broker status cmd)
				$cmd->setLogicalId(jMQTT::CLIENT_STATUS);
				$cmd->save();
			}
		}
	}

	log::add('jMQTT', 'info', 'Brokers status command tagged');
}

function raiseForceDepInstallFlag() {
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 5) {
		return;
	}

	config::save(FORCE_DEPENDANCY_INSTALL, 1, 'jMQTT');
}

function cleanLeakedInfoInEqpts() {
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 6) {
		return;
	}

	// list of broker configurations
	$configToRemove = array('mqttAddress',
							'mqttPort',
							'mqttId',
							'mqttUser',
							'mqttPass',
							'mqttPubStatus',
							'mqttIncTopic',
							'mqttTls',
							'mqttTlsCheck',
							'mqttTlsCaFile',
							'mqttTlsClientCertFile',
							'mqttTlsClientKeyFile',
							'api',
							'mqttPahoLog',
							'loglevel');

	// getNonBrokers() returns a 2-dimensional array containing eqpt eqLogics
	$eqNonBrokers = jMQTT::getNonBrokers();
	foreach ($eqNonBrokers as $eqLogics) {
		foreach ($eqLogics as $eqLogic) {

			foreach ($configToRemove as $configKey) {
				// remove leaked configuration
				$eqLogic->setConfiguration($configKey, null);
			}

			$eqLogic->save();
		}
	}

	log::add('jMQTT', 'info', 'Broker leaked info cleaned up in eqpts');
}

function cleanLeakedInfoInTemplates() {
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 6) {
		return;
	}

	// list of broker configurations
	$configToRemove = array('mqttAddress',
							'mqttPort',
							'mqttId',
							'mqttUser',
							'mqttPass',
							'mqttPubStatus',
							'mqttIncTopic',
							'mqttTls',
							'mqttTlsCheck',
							'mqttTlsCaFile',
							'mqttTlsClientCertFile',
							'mqttTlsClientKeyFile',
							'api',
							'mqttPahoLog',
							'loglevel');

	$templateFolderPath = dirname(__FILE__) . '/../data/template';

	foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
		try {
			$content = file_get_contents($templateFolderPath . '/' . $file);
			if (is_json($content)) {

				// decode template file content to json
				$templateContent = json_decode($content, true);

				// first key is the template itself
				$templateKey = array_keys($templateContent)[0];

				// if 'configuration' key exists in this template
				if (array_key_exists('configuration', $templateContent[$templateKey])) {

					// for each keys under 'configuration'
					foreach (array_keys($templateContent[$templateKey]['configuration']) as $configurationKey) {

						// if this configurationKey is in keys to remove
						if (in_array($configurationKey, $configToRemove)) {

							// remove it
							unset($templateContent[$templateKey]['configuration'][$configurationKey]);
						}
					}
				}

				// Save back template in the file
				$jsonExport = json_encode($templateContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
				file_put_contents($templateFolderPath . '/' . $file, $jsonExport);
			}
		} catch (Throwable $e) {}
	}

	log::add('jMQTT', 'info', 'Broker leaked info cleaned up in templates');
}

function splitJsonPathOfjMQTTCmd() {
	$version = config::byKey(VERSION, 'jMQTT', -1);
	if ($version >= 7) {
		return;
	}

	$eqLogics = jMQTT::byType('jMQTT');
	foreach ($eqLogics as $eqLogic) {

		// get info cmds of current eqLogic
		$infoCmds = jMQTTCmd::byEqLogicId($eqLogic->getId(), 'info');
		foreach ($infoCmds as $cmd) {
			// split topic and jsonPath of cmd
			$cmd->splitTopicAndJsonPath();
		}
	}
}

function jMQTT_install() {
	jMQTT_update();
}

function jMQTT_update() {
	
	// if version info is not in DB, it means it is a fresh install of jMQTT
	// and so we don't need to run these functions to adapt eqLogic structure/config
	// (even if plugin is disabled the config key stays)
	if (config::byKey(VERSION, 'jMQTT', -1) != -1) {
		// VERSION = 0
		migrateToMultiBrokerVersion();
		// VERSION = 1
		migrateToJsonVersion();
		// VERSION = 2
		disableAutoAddCmdOnBrokers();
		// VERSION = 3
		removePreviousDaemonCrons();
		installNewDependancies();
		// VERSION = 4
		tagBrokersStatusCmd();
		// VERSION = 5
		raiseForceDepInstallFlag();
		// VERSION = 6
		cleanLeakedInfoInEqpts();
		cleanLeakedInfoInTemplates();
		// VERSION = 7
		splitJsonPathOfjMQTTCmd();
		config::save(FORCE_DEPENDANCY_INSTALL, 1, 'jMQTT'); //TODO temporary before rest of evolution
	}

	// Update version next to upgrade operations
	config::save(VERSION, CURRENT_VERSION, 'jMQTT');
}

function jMQTT_remove() {
	
}

?>
