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
 * Force dependancy Install Flag handled by deamon_start (value = 1)
 */
define("FORCE_DEPENDANCY_INSTALL", 'forceDepInstall');


/**
 * version 1
 * Migrate the plugin to the new JSON version (implementing #76)
 * Return without doing anything if the new JSON version is already installed
 */
function migrateToJsonVersion() {

	/** @var cmd $cmd */
	foreach (cmd::searchConfiguration('', 'jMQTT') as $cmd) {
		jMQTT::logger('debug', 'migrate info command ' . $cmd->getName());
		$cmd->setConfiguration('parseJson', null);
		$cmd->setConfiguration('prevParseJson', null);
		$cmd->setConfiguration('jParent', null);
		$cmd->setConfiguration('jOrder', null);
		$cmd->save();
	}

	jMQTT::logger('info', 'migration to json#76 version done');
}

/**
 * version 2
 * Migrate the plugin to the new version with no auto_add_cmd on broker
 * Return without doing anything if the new version is already installed
 */
function disableAutoAddCmdOnBrokers() {

	//disable auto_add_cmd on Brokers eqpt because auto_add is removed for them
	foreach ((jMQTT::getBrokers()) as $broker) {
		$broker->setAutoAddCmd('0');
		$broker->save();
	}

	jMQTT::logger('info', 'migration to no auto_add_cmd for broker done');
}

/**
 * version 3
 * Migrate the plugin to the new daemon version
 * Return without doing anything if the new version is already installed
 */
function removePreviousDaemonCrons() {

	// remove all jMQTT old daemon crons
	do {
		$cron = cron::byClassAndFunction('jMQTT', 'daemon');
		if (is_object($cron)) $cron->remove(true);
		else break;
	}
	while (true);

	jMQTT::logger('info', 'removal of previous daemon cron done');
}

/**
 * version 3
 * Trigger installation of new dependancies
 * Return without doing anything if the new version is already installed
 */
function installNewDependancies() {

	//Jeedom Core Bug : the main thread will end by running the previous version dependancy_info()
	// (the old one says dependancies are met and it's cached...)
	// Even if we invalidate dependancies infos in cache, it's back just after
	// plugin::byId('jMQTT')->dependancy_info(true);

	// So best option is to remove old daemon dependancies
	// ***REMOVED*** Code removed due to side effect on other plugins. Problem handled by VERSION=5 and deamon_start() ***REMOVED***
}

function tagBrokersStatusCmd() {

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

	jMQTT::logger('info', 'Brokers status command tagged');
}

function raiseForceDepInstallFlag() {

	config::save(FORCE_DEPENDANCY_INSTALL, 1, 'jMQTT');
}

function cleanLeakedInfoInEqpts() {

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

	jMQTT::logger('info', 'Broker leaked info cleaned up in eqpts');
}

function cleanLeakedInfoInTemplates() {

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

	jMQTT::logger('info', 'Broker leaked info cleaned up in templates');
}

function splitJsonPathOfjMQTTCmd() {

	$eqLogics = jMQTT::byType('jMQTT');
	foreach ($eqLogics as $eqLogic) {

		// get info cmds of current eqLogic
		$infoCmds = jMQTTCmd::byEqLogicId($eqLogic->getId(), 'info');
		foreach ($infoCmds as $cmd) {
			// split topic and jsonPath of cmd
			$cmd->splitTopicAndJsonPath();
		}
	}

	jMQTT::logger('info', 'JsonPath splitted from topic for all jMQTT info commands');
}

function splitJsonPathOfTemplates() {

	$templateFolderPath = dirname(__FILE__) . '/../data/template';
	foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
		jMQTT::templateSplitJsonPathByFile($file);
	}

	jMQTT::logger('info', 'JsonPath splitted from topic for all templates');
}

function moveTopicOfjMQTTeqLogic() {

	$eqLogics = jMQTT::byType('jMQTT');
	foreach ($eqLogics as $eqLogic) {

		$eqLogic->moveTopicToConfiguration();
	}

	jMQTT::logger('info', 'Topic moved to configuration for all jMQTT equipments');
}

function moveTopicOfTemplates() {

	$templateFolderPath = dirname(__FILE__) . '/../data/template';
	foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
		jMQTT::moveTopicToConfigurationByFile($file);
	}

	jMQTT::logger('info', 'Topic moved to configuration for all templates');
}

function jMQTT_install() {
	jMQTT::logger('debug', 'install.php: jMQTT_install()');
	jMQTT_update(false);
}

function jMQTT_update($_direct=true) {
	if ($_direct)
		jMQTT::logger('debug', 'install.php: jMQTT_update()');

	// if version info is not in DB, it means it is a fresh install of jMQTT
	// and so we don't need to run these functions to adapt eqLogic structure/config
	// (even if plugin is disabled the config key stays)
	$versionFromDB = config::byKey(VERSION, 'jMQTT', -1);
	if ($versionFromDB != -1) {

		// VERSION = 1
		if ($versionFromDB < 1) {
			migrateToJsonVersion();
			config::save(VERSION, 1, 'jMQTT');
		}

		// VERSION = 2
		if ($versionFromDB < 2) {
			disableAutoAddCmdOnBrokers();
			config::save(VERSION, 2, 'jMQTT');
		}

		// VERSION = 3
		if ($versionFromDB < 3) {
			removePreviousDaemonCrons();
			installNewDependancies();
			config::save(VERSION, 3, 'jMQTT');
		}

		// VERSION = 4
		if ($versionFromDB < 4) {
			tagBrokersStatusCmd();
			config::save(VERSION, 4, 'jMQTT');
		}

		// VERSION = 5
		if ($versionFromDB < 5) {
			raiseForceDepInstallFlag();
			config::save(VERSION, 5, 'jMQTT');
		}

		// VERSION = 6
		if ($versionFromDB < 6) {
			cleanLeakedInfoInEqpts();
			cleanLeakedInfoInTemplates();
			config::save(VERSION, 6, 'jMQTT');
		}

		// VERSION = 7
		if ($versionFromDB < 7) {
			splitJsonPathOfjMQTTCmd();
			splitJsonPathOfTemplates();
			raiseForceDepInstallFlag();
			config::save(VERSION, 7, 'jMQTT');
		}

		// VERSION = 8
		if ($versionFromDB < 8) {
			moveTopicOfjMQTTeqLogic();
			moveTopicOfTemplates();
			config::save(VERSION, 8, 'jMQTT');
		}

		// VERSION = 9
		if ($versionFromDB < 9) {
			raiseForceDepInstallFlag();
			config::save(VERSION, 9, 'jMQTT');
		}
	}
	else
		config::save(VERSION, 9, 'jMQTT');
}

function jMQTT_remove() {
	jMQTT::logger('debug', 'install.php: jMQTT_remove()');
	cache::delete('jMQTT::' . jMQTT::CACHE_DAEMON_CONNECTED);
}

?>
