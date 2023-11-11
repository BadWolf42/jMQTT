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

require_once __DIR__ . '/../../../../core/php/core.inc.php';

if (!jeedom::apiAccess(init('apikey'), 'jMQTT')) {	// Security
	echo 'Unauthorized access.';
	$log_start = sprintf(
		__("Accès non autorisé depuis %s", __FILE__),
		$_SERVER['REMOTE_ADDR'],
	);
	if (init('apikey') != '')
		jMQTT::logger(
			'error',
			$log_start . sprintf(
				__(", avec la clé API commençant par %.8s...", __FILE__),
				init('apikey')
			)
		);
	else
		jMQTT::logger(
			'error',
			$log_start . ' ' . __(", sans clé API.", __FILE__)
		);
	die();
}
if ($_SERVER['REQUEST_METHOD'] != 'POST') {			// NOT POST, used by ping, we just close the connection
	die();
}

require_once __DIR__ . '/../../core/class/jMQTT.class.php';

$ruid = init('uid');								// Collect Remote PID and PORT at connection
$head = __("Démon", __FILE__) . ' [' . $ruid . ']: ';
$received = file_get_contents("php://input");		// Get page full content
// jMQTT::logger('debug', $head . sprintf(__(Données reçues '%1\$s'", __FILE__), $received));
$messages = json_decode($received, true);			// Try to decode json -> can throw Exception
if (is_null($messages) || !is_array($messages)) {	// Only expect an array of messages
	jMQTT::logger(
		'error',
		$head . sprintf(
			__("Format JSON erroné: '%1\$s'", __FILE__),
			$received
		)
	);
	die();
}
foreach($messages as $message) {					// Iterate through the messages
	if (!isset($message['cmd'])) {					// No cmd supplied
		jMQTT::logger(
			'error',
			$head . sprintf(
				__("Paramètre cmd manquant dans le message: '%2\$s'", __FILE__),
				json_encode($message)
			)
		);
		continue;
	}
	$authorized = jMQTTDaemon::valid_uid($ruid);			// Evaluate each time capability of this daemon to send data to Jeedom
	if (!$authorized && ($message['cmd'] != 'daemonUp')) { // Check if daemon is in a correct state or deny the message
		if (is_null($authorized))
			jMQTT::logger(
				'debug',
				$head . sprintf
				(__("Impossible d'autoriser la cmd '%2\$s' avant la commande 'daemonUp': '%3\$s'", __FILE__),
				$message['cmd'],
				json_encode($message)
			)
		);
		else
			jMQTT::logger(
				'debug',
				$head . sprintf(
					__("Message refusé (démon invalide) : '%2\$s'", __FILE__),
					json_encode($message)
				)
			);
		continue;
	}
	switch ($message['cmd']) {						// Handle commands from daemon
		case 'messageIn':
			if (!isset($message['id']) || !isset($message['topic']) || !isset($message['payload']))
				break;
			jMQTTComFromDaemon::msgIn(
				$message['id'],
				$message['topic'],
				$message['payload'],
				$message['qos'],
				$message['retain']
			);
			continue 2;								// Next foreach iteration

		case 'brokerUp':								// {"cmd":"brokerUp", "id":string}
			if (!isset($message['id']))
				break;
			jMQTTComFromDaemon::brkUp($message['id']);
			continue 2;								// Next foreach iteration

		case 'brokerDown':								// {"cmd":"brokerDown", "id":string}
			if (!isset($message['id']))
				break;
			jMQTTComFromDaemon::brkDown($message['id']);
			continue 2;								// Next foreach iteration

		case 'realTimeStarted':						// {"cmd":"realTimeStart", "id":string}
			if (!isset($message['id']))
				break;
			jMQTTComFromDaemon::realTimeStarted($message['id']);
			continue 2;								// Next foreach iteration

		case 'realTimeStopped':						// {"cmd":"realTimeStopped", "id":string, "nbMsgs":int}
			if (!isset($message['id']) || !isset($message['nbMsgs']))
				break;
			jMQTTComFromDaemon::realTimeStopped($message['id'], $message['nbMsgs']);
			continue 2;								// Next foreach iteration

		case 'hb':									// {"cmd":"hb"}
			jMQTTComFromDaemon::hb($ruid);
			continue 2;								// Next foreach iteration

		case 'daemonUp':							// {"cmd":"daemonUp"}
			jMQTTComFromDaemon::daemonUp($ruid);
			continue 2;								// Next foreach iteration

		case 'daemonDown':							// {"cmd":"daemonDown"}
			jMQTTComFromDaemon::daemonDown($ruid);
			continue 2;								// Next foreach iteration

		default:
			jMQTT::logger(
				'error',
				$head . sprintf(
					__("Commande inconnue dans le message: '%2\$s'", __FILE__),
					json_encode($message)
				)
			);
			continue 2;								// Next foreach iteration
	}

// TODO (medium) Implemented for later
/*
		case 'value':								// {"cmd":"value", "c":[string], "v":string} # c: list of cmdId v: value to set
			if (!isset($message['c']) || !isset($message['v']))
				break;
			foreach($message['c'] as $cmdId)
				jMQTTComFromDaemon::value($cmdId, $message['v']);
			continue 2;								// Next foreach iteration
*/

	// All cmds the bad parameters are handled here
	jMQTT::logger(
		'error',
		$head . sprintf(
			__("Paramètre manquant pour la cmd '%2\$s' : '%3\$s'", __FILE__),
			$message['cmd'],
			json_encode($message)
		)
	);
}
