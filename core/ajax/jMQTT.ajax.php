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
	define('PATH_TPLTS', __DIR__ . '/../../data/template');

	if (init('action') == 'fileupload') { // Does NOT work if placed after "ajax::init()", because using some parameters in GET
		log::add('jMQTT', 'info', 'file upload "' . $_FILES['file']['name'] . '"');
		if (!isset($_FILES['file'])) {
			throw new Exception(__('Aucun fichier trouvé. Vérifiez le paramètre PHP (post size limit)', __FILE__));
		}
		$extension = strtolower(strrchr($_FILES['file']['name'], '.'));
		if (!in_array($extension, array('.crt', '.key', '.json', '.pem'))) {
			throw new Exception('Extension du fichier non autorisée : ' . $extension);
		}
		if (filesize($_FILES['file']['tmp_name']) > 500000) {
			throw new Exception(__('Le fichier est trop gros (maximum 500Ko)', __FILE__));
		}
		if (init('dir') == 'template') {
			$uploaddir = PATH_TPLTS;
		} elseif (init('dir') == 'certs') {
			$uploaddir = realpath(dirname(__FILE__) . '/../../' . jMQTT::PATH_CERTIFICATES);
		} else {
			throw new Exception(__('Téléversement invalide', __FILE__));
		}
		if (!file_exists($uploaddir)) {
			mkdir($uploaddir);
		}
		if (!file_exists($uploaddir)) {
			throw new Exception(__('Répertoire de téléversement non trouvé : ', __FILE__) . $uploaddir);
		}
		if (file_exists($uploaddir . '/' . $_FILES['file']['name'])) {
			throw new Exception(__('Impossible de téléverser le fichier car il existe déjà, par sécurité il faut supprimer le fichier existant avant de le remplacer.', __FILE__));
		}
		if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploaddir . '/' . $_FILES['file']['name'])) {
			throw new Exception(__('Impossible de déplacer le fichier temporaire', __FILE__));
		}
		if (!file_exists($uploaddir . '/' . $_FILES['file']['name'])) {
			throw new Exception(__('Impossible de téléverser le fichier (limite du serveur web ?)', __FILE__));
		}
		// After template file imported
		if (init('dir') == 'template') {
			// Adapt template for the new jsonPath field
			jMQTT::templateSplitJsonPathByFile($_FILES['file']['name']);
			// Adapt template for the topic in configuration
			jMQTT::moveTopicToConfigurationByFile($_FILES['file']['name']);
		}
		ajax::success($_FILES['file']['name']);
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
			throw new Exception(__('Pas d\'équipement jMQTT avec l\'id fourni', __FILE__) . ' (id=' . init('id') . ')');
		}
		$template = jMQTT::templateByName(init('name'));
		$eqpt->applyATemplate($template, init('topic'), init('keepCmd'));
		ajax::success();
	}

	if (init('action') == 'createTemplate') {
		$eqpt = jMQTT::byId(init('id'));
		if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
			throw new Exception(__('Pas d\'équipement jMQTT avec l\'id fourni', __FILE__) . ' (id=' . init('id') . ')');
		}
		$eqpt->createTemplate(init('name'));
		ajax::success();
	}

	// To change the equipment automatic inclusion mode
	if (init('action') == 'changeIncludeMode') {
		$new_broker = jMQTT::getBrokerFromId(init('id'));
		$new_broker->changeIncludeMode(init('mode'));
		ajax::success();
	}

	if (init('action') == 'getMqttClientInfo') {
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		$new_broker = jMQTT::getBrokerFromId(init('id'));
		ajax::success($new_broker->getMqttClientInfo());
	}

	if (init('action') == 'getMqttClientState') {
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		$new_broker = jMQTT::getBrokerFromId(init('id'));
		ajax::success($new_broker->getMqttClientState());
	}

	if (init('action') == 'startMqttClient') {
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		$new_broker = jMQTT::getBrokerFromId(init('id'));
		ajax::success($new_broker->startMqttClient(true));
	}

	if (init('action') == 'moveToBroker') {
		if (!isConnect('admin')) {
			throw new Exception(__('401 - Accès non autorisé', __FILE__));
		}
		/** @var jMQTT $eqpt */
		$eqpt = jMQTT::byId(init('id'));
		if (!is_object($eqpt) || $eqpt->getEqType_name() != jMQTT::class) {
			throw new Exception(__('Pas d\'équipement jMQTT avec l\'id fourni', __FILE__) . ' (id=' . init('id') . ')');
		}
		$old_broker_id = $eqpt->getBrkId();
		$new_broker = jMQTT::getBrokerFromId(init('brk_id'));
		log::add('jMQTT', 'info', 'déplace l\'équipement ' . $eqpt->getName() . ' vers le broker ' . $new_broker->getName());
		$eqpt->setBrkId($new_broker->getId());
		$eqpt->cleanEquipment();
		$eqpt->save();

		ajax::success();
	}

	if (init('action') == 'filedelete') {
		$certname = init('name');
		log::add('jMQTT', 'info', 'file delete "' . $certname . '"');
		if (init('dir') == 'template') {
			$uploaddir = PATH_TPLTS;
		} elseif (init('dir') == 'certs') {
			$uploaddir = realpath(dirname(__FILE__) . '/../../' . jMQTT::PATH_CERTIFICATES);
		} else {
			throw new Exception(__('Suppression invalide', __FILE__));
		}
		if (!file_exists($uploaddir . '/' . $certname)) {
			throw new Exception(__('Impossible de supprimer le fichier, car il n\'existe pas.', __FILE__));
		} else {
			// Check if cert is used by a Broker!
			foreach (jMQTT::getBrokers() as $broker)
				if ($broker->isCertUsed($certname))
					throw new Exception(__('Impossible de supprimer le fichier, car il est utilisé par le Broker "'.$broker->getName().'".', __FILE__));
			unlink($uploaddir . '/' . $certname);
		}
		ajax::success();
	}

/*    if (init('action') == 'filelist') {
		log::add('jMQTT', 'info', 'file list "' . init('dir') . '"');
		if (init('dir') == 'template') {
			$uploaddir = PATH_TPLTS;
			$patern = array('.json');
		} elseif (init('dir') == 'certs') {
			$uploaddir = realpath(dirname(__FILE__) . '/../../' . jMQTT::PATH_CERTIFICATES);
			$patern = array('.crt', '.key', '.pem');
		} else {
			throw new Exception(__('Suppression invalide', __FILE__));
		}
		$res = [];
		foreach (ls($uploaddir) as $file) {
			if (in_array(strtolower(strrchr($file, '.')), $patern))
				$res[] = $file;
		}
		ajax::success($res);
	}
*/

	throw new Exception(__('Aucune methode Ajax correspondante à : ', __FILE__) . init('action'));
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}
?>
