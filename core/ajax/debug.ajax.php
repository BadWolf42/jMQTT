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

	ajax::init();


	if (init('action') == 'configSet') {
		jMQTT::logger('debug', init('action').': key='.init('key').' value='.init('val'));
		config::save(init('key'), json_decode(init('val')), 'jMQTT');
		ajax::success();
	}
	if (init('action') == 'configDel') {
		jMQTT::logger('debug', init('action').': key='.init('key'));
		config::remove(init('key'), 'jMQTT');
		ajax::success();
	}

	if (init('action') == 'cacheSet') {
		jMQTT::logger('debug', init('action').': key='.init('key').' value='.init('val'));
		cache::set(init('key'), json_decode(init('val')));
		ajax::success();
	}
	if (init('action') == 'cacheDel') {
		jMQTT::logger('debug', init('action').': key='.init('key'));
		cache::delete(init('key'));
		ajax::success();
	}

/*
	if (init('action') == 'getTemplateList') {
		$cache = cache::delete('hour');
		ajax::success();
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
*/

	throw new Exception(__('Aucune méthode Ajax ne correspond à : ', __FILE__) . init('action'));
	/*     * *********Catch exeption*************** */
} catch (Exception $e) {
	ajax::error(displayException($e), $e->getCode());
}
?>
