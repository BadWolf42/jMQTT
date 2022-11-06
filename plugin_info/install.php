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
		jMQTT::logger('debug', __("Migration de la commande info: ", __FILE__) . $cmd->getHumanName());
		$cmd->setConfiguration('parseJson', null);
		$cmd->setConfiguration('prevParseJson', null);
		$cmd->setConfiguration('jParent', null);
		$cmd->setConfiguration('jOrder', null);
		$cmd->save();
	}

	jMQTT::logger('info', __("Migration vers la version json#76", __FILE__));
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

	jMQTT::logger('info', __("Désactivation de l'ajout automatique de commandes sur les Broker", __FILE__));
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

	jMQTT::logger('info', __("Suppression du démon cron précédent", __FILE__));
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
	jMQTT::logger('info', __("Ajout de tags sur le statut des Broker", __FILE__));
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
							'mqttLwt',
							'mqttLwtTopic',
							'mqttLwtOnline',
							'mqttLwtOffline',
							'mqttIncTopic',
							'mqttTls',
							'mqttTlsCheck',
							'mqttTlsCaFile',
							'mqttTlsClient',
							'mqttTlsClientCertFile',
							'mqttTlsClientKeyFile',
							'mqttApi',
							'mqttApiTopic',
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

	jMQTT::logger('info', __("Equipements nettoyés des informations du Broker", __FILE__));
}

function cleanLeakedInfoInTemplates() {
	// list of broker configurations
	$configToRemove = array('mqttAddress',
							'mqttPort',
							'mqttId',
							'mqttUser',
							'mqttPass',
							'mqttPubStatus',
							'mqttLwt',
							'mqttLwtTopic',
							'mqttLwtOnline',
							'mqttLwtOffline',
							'mqttIncTopic',
							'mqttTls',
							'mqttTlsCheck',
							'mqttTlsCaFile',
							'mqttTlsClient',
							'mqttTlsClientCertFile',
							'mqttTlsClientKeyFile',
							'mqttApi',
							'mqttApiTopic',
							'mqttPahoLog',
							'loglevel');
	$templateFolderPath = __DIR__ . '/../data/template';
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
	jMQTT::logger('info', __("Templates nettoyés des informations du Broker", __FILE__));
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
	jMQTT::logger('info', __("JsonPath séparé du Topic pour tous les commandes info jMQTT", __FILE__));
}

function splitJsonPathOfTemplates() {
	$templateFolderPath = __DIR__ . '/../data/template';
	foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
		jMQTT::templateSplitJsonPathByFile($file);
	}
	jMQTT::logger('info', __("JsonPath séparé du Topic pour tous les Templates jMQTT", __FILE__));
}

function moveTopicOfjMQTTeqLogic() {
	$eqLogics = jMQTT::byType('jMQTT');
	foreach ($eqLogics as $eqLogic) {
		$eqLogic->moveTopicToConfiguration();
	}
	jMQTT::logger('info', __("Topics déplacé vers la configuration pour tous les équipements jMQTT", __FILE__));
}

function moveTopicOfTemplates() {
	$templateFolderPath = __DIR__ . '/../data/template';
	foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
		jMQTT::moveTopicToConfigurationByFile($file);
	}
	jMQTT::logger('info', __("Topics déplacé vers la configuration pour tous les Templates jMQTT", __FILE__));
}

function convertBatteryStatus() {
	foreach (jMQTT::byType('jMQTT') as $eqLogic) {
		// Protect already modified Eq
		$batId = $eqLogic->getBatteryCmd();
		if ($batId != false && $batId != '') {
			$cmd = jMQTTCmd::byId($batId);
			jMQTT::logger('info', sprintf(__("#%1\$s# définit DÉJÀ la batterie de #%2\$s#", __FILE__), $cmd->getHumanName(), $eqLogic->getHumanName()));
			continue;
		}
		// get info cmds of current eqLogic
		foreach (jMQTTCmd::byEqLogicId($eqLogic->getId(), 'info') as $cmd) {
			// Old isBattery()
			if ($cmd->getType() == 'info' && ($cmd->getGeneric_type() == 'BATTERY' || preg_match('/(battery|batterie)$/i', $cmd->getName()))) {
				$eqLogic->setConfiguration(jMQTT::CONF_KEY_BATTERY_CMD, $cmd->getId());
				jMQTT::logger('info', sprintf(__("#%1\$s# définit la batterie de #%2\$s#", __FILE__), $cmd->getHumanName(), $eqLogic->getHumanName()));
				$eqLogic->save();
			}
		}
	}
	jMQTT::logger('info', __("Commandes batterie définies directement sur les équipements jMQTT", __FILE__));
}

function moveCertsInDb() {
	$oldPath = realpath(dirname(__FILE__) . '/../data/jmqtt/certs');
	// for each brokers
	foreach ((jMQTT::getBrokers()) as $broker) {
		$broker->setConfiguration(jMQTT::CONF_KEY_MQTT_PROTO, boolval($broker->getConfiguration('mqttTls', 0)) ? 'mqtts' : 'mqtt');
		$broker->setConfiguration('mqttTls', null);
		$cert = $broker->getConfiguration('mqttTlsCaFile', '');
		$broker->setConfiguration('mqttTlsCaFile', null);
		if ($cert != '')
			$broker->setConfiguration(jMQTT::CONF_KEY_MQTT_TLS_CA, file_get_contents($oldPath.'/'.$cert));
		$cert = $broker->getConfiguration('mqttTlsClientCertFile', '');
		$broker->setConfiguration('mqttTlsClientCertFile', null);
		if ($cert != '')
			$broker->setConfiguration(jMQTT::CONF_KEY_MQTT_TLS_CLI_CERT, file_get_contents($oldPath.'/'.$cert));
		$cert = $broker->getConfiguration('mqttTlsClientKeyFile', '');
		$broker->setConfiguration('mqttTlsClientKeyFile', null);
		if ($cert != '')
			$broker->setConfiguration(jMQTT::CONF_KEY_MQTT_TLS_CLI_KEY, file_get_contents($oldPath.'/'.$cert));
		$broker->save();
	}
	// rm -rf $oldPath
	foreach (array_diff(scandir($oldPath), array('.','..')) as $file) {
		unlink($oldPath.'/'.$file);
	}
	rmdir($oldPath);
	jMQTT::logger('info', __("Certificats déplacés dans la base de donnée", __FILE__));
}

function v12_modifyConfKeysInBrk() {
	// for each Broker
	foreach ((jMQTT::getBrokers()) as $broker) {
		try {
			// Set 'mqttLwt' config only if not already set
			if ($broker->getConfiguration(jMQTT::CONF_KEY_MQTT_LWT, 'NotThere') == 'NotThere') {
				// copy 'mqttPubStatus' value to 'mqttLwt' in broker config
				$broker->setConfiguration(jMQTT::CONF_KEY_MQTT_LWT, $broker->getConfiguration('mqttPubStatus', '0'));
			}
			// delete 'mqttPubStatus' from broker config
			$broker->setConfiguration('mqttPubStatus', null);

			// Set 'mqttApi' config only if not already set
			if ($broker->getConfiguration(jMQTT::CONF_KEY_MQTT_API, 'NotThere') == 'NotThere') {
				// copy 'api' value to 'mqttApi' in broker config
				$broker->setConfiguration(jMQTT::CONF_KEY_MQTT_API, ($broker->getConfiguration('api', '0') == 'enable') ? '1' : '0');
			}
			// delete 'api' from broker config
			$broker->setConfiguration('api', null);

			// remove old include_mode cache key
			$broker->setCache('include_mode', null);

			$broker->save();
		} catch (Throwable $e) {
			if (log::getLogLevel(jMQTT::class) > 100)
				jMQTT::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __FUNCTION__, $e->getMessage()));
			else
				jMQTT::logger('error', str_replace("\n",' </br> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
							"</br>@Stack: %3\$s,</br>@BrokerId: %4\$s.",
							__FUNCTION__, $e->getMessage(), $e->getTraceAsString(), $broker->getId())));
		}
	}
}

function v12_modifyBrkIdConfKeyInEq() {
	foreach (jMQTT::byType(jMQTT::class) as $eqLogic) {
		try {
			if ($eqLogic->getType() == jMQTT::TYP_BRK) {
				jMQTT::logger('debug', $eqLogic->getHumanName() . ' est un Broker');
				continue;
			}
			// Set 'eqLogic' config only if not already set
			if ($eqLogic->getConfiguration(jMQTT::CONF_KEY_BRK_ID, 'NotThere') == 'NotThere') {
				// copy 'brkId' value to 'eqLogic' in broker config
				$eqLogic->setConfiguration(jMQTT::CONF_KEY_BRK_ID, $eqLogic->getConfiguration('brkId', -1));
				// delete 'brkId' from broker config
				$eqLogic->setConfiguration('brkId', null);
				$eqLogic->save(true); // Direct save to avoid issues while saving
			}
		} catch (Throwable $e) {
			if (log::getLogLevel(jMQTT::class) > 100)
				jMQTT::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __FUNCTION__, $e->getMessage()));
			else
				jMQTT::logger('error', str_replace("\n",' </br> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
							"</br>@Stack: %3\$s,</br>@EqlogicId: %4\$s.",
							__FUNCTION__, $e->getMessage(), $e->getTraceAsString(), $eqLogic->getId())));
		}
	}
	jMQTT::logger('info', __("Clés de configuration des Brokers jMQTT modifiées", __FILE__));
}

function v13_modifyClientIdInBrk() {
	// for each Broker
	foreach ((jMQTT::getBrokers()) as $broker) {
		try {
			// Set 'mqttId' & 'mqttIdValue' config, only if 'mqttIdValue' config not already set
			if ($broker->getConfiguration('mqttIdValue', 'NotThere') == 'NotThere') {
				$mqttIdValue = $broker->getConfiguration('mqttId', '');
				// Copy 'mqttId' into 'mqttIdValue'
				$broker->setConfiguration('mqttIdValue', $mqttIdValue);

				// Set 'mqttId' to '1' if 'mqttIdValue' is set, '0' otherwise
				$broker->setConfiguration('mqttId', ''.intval($mqttIdValue != ''));

				// Save eqBroker if modified
				$broker->save();
			}
		} catch (Throwable $e) {
			if (log::getLogLevel(jMQTT::class) > 100)
				jMQTT::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __FUNCTION__, $e->getMessage()));
			else
				jMQTT::logger('error', str_replace("\n",' </br> ', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__).
							"</br>@Stack: %3\$s,</br>@BrokerId: %4\$s.",
							__FUNCTION__, $e->getMessage(), $e->getTraceAsString(), $broker->getId())));
		}
	}
	jMQTT::logger('info', __("Configuration des Client-Id mises à jour", __FILE__));
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

		// VERSION = 10
		if ($versionFromDB < 10) {
			convertBatteryStatus();
			config::save(VERSION, 10, 'jMQTT');
		}

		// VERSION = 11
		if ($versionFromDB < 11) {
			moveCertsInDb();
			config::save(VERSION, 11, 'jMQTT');
		}

		// VERSION = 12
		if ($versionFromDB < 12) {
			v12_modifyConfKeysInBrk();
			v12_modifyBrkIdConfKeyInEq();
			config::save(VERSION, 12, 'jMQTT');
		}

		// VERSION = 13
		if ($versionFromDB < 13) {
			v13_modifyClientIdInBrk();
			config::save(VERSION, 13, 'jMQTT');
		}
	}
	else
		config::save(VERSION, 13, 'jMQTT');
}

function jMQTT_remove() {
	jMQTT::logger('debug', 'install.php: jMQTT_remove()');
	cache::delete('jMQTT::' . jMQTT::CACHE_DAEMON_CONNECTED);
}

?>
