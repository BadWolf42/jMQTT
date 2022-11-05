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

try {
	require_once __DIR__ . '/../../../../core/php/core.inc.php';
	include_file('core', 'authentification', 'php');

	if (!isConnect('admin')) {
		throw new Exception(__('401 - Accès non autorisé', __FILE__));
	}

	if (init('action') == 'fileupload') { // Does NOT work if placed after "ajax::init()", because using some parameters in GET
		if (!isset($_FILES['file'])) {
			throw new Exception(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
		}
		$extension = strtolower(strrchr($_FILES['file']['name'], '.'));
		if (!in_array($extension, array('.crt', '.key', '.json', '.pem'))) {
			throw new Exception(sprintf(__("L'extension de fichier '%s' n'est pas autorisée", __FILE__), $extension));
		}
		if (filesize($_FILES['file']['tmp_name']) > 500000) {
			throw new Exception(__('Le fichier est trop gros (maximum 500Ko)', __FILE__));
		}
		if (init('dir') == 'template') {
			$uploaddir = realpath(__DIR__ . '/../../' . jMQTT::PATH_TEMPLATES_PERSO);
		} else {
			throw new Exception(__('Téléversement invalide', __FILE__));
		}
		if (!file_exists($uploaddir)) {
			mkdir($uploaddir);
		}
		if (!file_exists($uploaddir)) {
			throw new Exception(__('Répertoire de téléversement non trouvé : ', __FILE__) . $uploaddir);
		}
		$fname = $_FILES['file']['name'];
		if (file_exists($uploaddir . '/' . $fname)) {
			throw new Exception(__('Impossible de téléverser le fichier car il existe déjà, par sécurité il faut supprimer le fichier existant avant de le remplacer.', __FILE__));
		}
		if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploaddir . '/' . $fname)) {
			throw new Exception(__('Impossible de déplacer le fichier temporaire', __FILE__));
		}
		if (!file_exists($uploaddir . '/' . $fname)) {
			throw new Exception(__('Impossible de téléverser le fichier (limite du serveur web ?)', __FILE__));
		}
		// After template file imported
		if (init('dir') == 'template') {
			// Adapt template for the new jsonPath field
			jMQTT::templateSplitJsonPathByFile($fname);
			// Adapt template for the topic in configuration
			jMQTT::moveTopicToConfigurationByFile($fname);
		}
		jMQTT::logger('info', sprintf(__("Template %s correctement téléversée", __FILE__), $fname));
		ajax::success($fname);
	}

	ajax::init();

	if (init('action') == 'getTemplateList') {
		ajax::success(jMQTT::templateList());
	}

	if (init('action') == 'getTemplateByFile') {
		ajax::success(jMQTT::templateByFile(init('file')));
	}

	if (init('action') == 'deleteTemplateByFile') {
		if (!jMQTT::deleteTemplateByFile(init('file')))
			throw new Exception(__('Impossible de supprimer le fichier', __FILE__));
		ajax::success(true);
	}

	if (init('action') == 'applyTemplate') {
		$eqpt = jMQTT::byId(init('id'));
		if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
			throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s", __FILE__), init('id')));
		}
		$template = jMQTT::templateByName(init('name'));
		$eqpt->applyATemplate($template, init('topic'), init('keepCmd'));
		ajax::success();
	}

	if (init('action') == 'createTemplate') {
		$eqpt = jMQTT::byId(init('id'));
		if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
			throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s", __FILE__), init('id')));
		}
		$eqpt->createTemplate(init('name'));
		ajax::success();
	}

	// Enable/Disable Real Time mode on this Broker
	if (init('action') == 'changeRealTimeMode') {
		$broker = jMQTT::getBrokerFromId(init('id'));
		$broker->changeRealTimeMode(init('mode'), init('subscribe'), init('exclude'), init('retained'));
		ajax::success();
	}

	// Add a new command on an existing jMQTT equipment
	if (init('action') == 'newCmd') {
		$eqpt = jMQTT::byId(init('id'));
		if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
			throw new Exception(sprintf(__("Pas d'équipement jMQTT avec l'id %s", __FILE__), init('id')));
		}
		$new_cmd = jMQTTCmd::newCmd($eqpt, init('name'), init('topic'), init('jsonPath'));
		$new_cmd->save();
		ajax::success(array('id' => $new_cmd->getId(), 'human' => $new_cmd->getHumanName()));
	}

	if (init('action') == 'startMqttClient') {
		$broker = jMQTT::getBrokerFromId(init('id'));
		ajax::success($broker->startMqttClient(true));
	}

	if (init('action') == 'sendLoglevel') {
		jMQTT::toDaemon_setLogLevel(init('level'));
		ajax::success();
	}

	if (init('action') == 'updateUrlOverride') {
		config::save('urlOverrideEnable', init('valEn'), 'jMQTT');
		config::save('urlOverrideValue', init('valUrl'), 'jMQTT');
		ajax::success();
	}

	if (init('action') == 'realTimeGet') {
		$broker = jMQTT::getBrokerFromId(init('id'));
		$_file = jeedom::getTmpFolder('jMQTT').'/rt' . $broker->getId() . '.json';
		if (!file_exists($_file))
			ajax::success([]);
		// Read content from file without error handeling!
		$content = file_get_contents($_file);
		// Decode template file content to json (or raise)
		$json = json_decode($content, true);
		// Get filtering data
		$since = init('since', '');
		// Search for compatible eqLogic on this Broker
		$brk_elogics = jMQTT::byBrkId($broker->getId());
		$res = [];
		// Function to filter array on date
		function since_filter($val) { global $since; return $val['date'] > $since; }
		// Filter array and search for matching eqLogic on remainings
		foreach (array_filter($json, 'since_filter') as $msg) {
			$eqNames = '';
			foreach ($brk_elogics as $eqpt) {
				if (mosquitto_topic_matches_sub($eqpt->getTopic(), $msg['topic']))
					$eqNames .= '<br />#'.$eqpt->getHumanName().'#';
			}
			$msg['existing'] = $eqNames;
			$res[] = $msg;
		}
		// Return result
		ajax::success($res);
	}

	if (init('action') == 'realTimeClear') {
		$broker = jMQTT::getBrokerFromId(init('id'));
		$broker->toDaemon_realTimeClear();
		ajax::success();
	}

	if (init('action') == 'mosquittoInstall') {
		jMQTT::mosquittoInstall();
		ajax::success(jMQTT::mosquittoCheck());
	}

	if (init('action') == 'mosquittoRepare') {
		jMQTT::mosquittoRepare();
		ajax::success(jMQTT::mosquittoCheck());
	}

	if (init('action') == 'mosquittoRemove') {
		sleep(3);
		jMQTT::mosquittoRemove();
		ajax::success(jMQTT::mosquittoCheck());
	}

	throw new Exception(__('Aucune méthode Ajax ne correspond à : ', __FILE__) . init('action'));
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}
?>
