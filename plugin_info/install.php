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


function raiseForceDepInstallFlag() {
	config::save('forceDepInstall', 1, 'jMQTT');
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
	try {
		$content = file_get_contents(__DIR__ . '/info.json');
		$data = json_decode($content, true);
		$pluginVersion = $data['pluginVersion'];
	} catch (Throwable $e) {
		log::add('jMQTT', 'warning', __("Impossible de récupérer le numéro de version dans le fichier info.json, ceci ce devrait pas arriver !", __FILE__));
		$pluginVersion = 0;
	}

	$version = @intval(config::byKey('version', 'jMQTT', $pluginVersion));

	while (++$version <= $pluginVersion) {
		try {
			$file = __DIR__ . '/../resources/update/' . $version . '.php';
			if (file_exists($file)) {
				log::add('jMQTT', 'debug', sprintf(__("Version %d : Application des modifications", __FILE__), $version));
				include $file;
				log::add('jMQTT', 'debug', sprintf(__("Version %d : Modifications appliquées", __FILE__), $version));
			}
		} catch (Throwable $e) {
			log::add('jMQTT', 'error', str_replace("\n",'<br/>',
				sprintf(__("Version %1\$d : Exception lors de l'application des modifications : %2\$s", __FILE__)."<br/>@Stack: %3\$s.",
						$version, $e->getMessage(), $e->getTraceAsString())
			));
		}
	}

	config::save('version', $pluginVersion, 'jMQTT');

	jMQTT::pluginStats($_direct ? 'update' : 'install');
}

function jMQTT_remove() {
	jMQTT::logger('debug', 'install.php: jMQTT_remove()');
	jMQTT::pluginStats('uninstall');
	cache::delete('jMQTT::' . jMQTT::CACHE_DAEMON_CONNECTED);
}

?>
