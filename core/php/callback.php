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

require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";
require_once dirname(__FILE__) . '/../../core/class/jMQTT.class.php';

if (!jeedom::apiAccess(init('apikey'), 'jMQTT')) {	// Security
	echo 'Unauthorized access.';
	jMQTT::logger('error', sprintf('Unauthorized access from %s with APIKEY "%.8s..."', $_SERVER['REMOTE_ADDR'], init('apikey')));
	die();
}
if ($_SERVER['REQUEST_METHOD'] != 'POST') {			// NOT POST, used by ping, we just close the connection
	die();
}
$ruid = init('uid');								// Collect Remote PID and PORT at connection
$received = file_get_contents("php://input");		// Get page full content
// jMQTT::logger('debug', sprintf('Daemon [%s]: Received "%s"', $ruid, $received));
$messages = json_decode($received, true);			// Try to decode json -> can throw Excepetion
if (is_null($messages) || !is_array($messages)) {	// Only expect an array of messages
	jMQTT::logger('error', sprintf('Daemon [%s]: Incorrect JSON format: "%s".', $ruid, $received));
	die();
}
foreach($messages as $message) {					// Iterate through the messages
	if (!isset($message['cmd'])) {					// No cmd supplied
		jMQTT::logger('error', sprintf('Daemon [%s]: Missing id or cmd, see: "%s".', $ruid, json_encode($message)));
		continue;
	}
	switch ($message['cmd']) {						// Handle commands from daemon
		case 'messageIn':
			if (!isset($message['id']) || !isset($message['topic']) || !isset($message['payload']) || !isset($message['qos']) || !isset($message['retain']))
				break;
			jMQTT::on_mqtt_message($message['id'], $message['topic'], $message['payload'], $message['qos'], $message['retain']);
			continue 2;								// Next foreach iteration

		case 'brokerUp':								// {"cmd":"brokerUp", "id":string}
			if (!isset($message['id']))
				break;
			jMQTT::logger('debug', sprintf('Daemon [%s]: Broker %s connected', $ruid, $message['id']));
			jMQTT::on_mqtt_connect($message['id']);
			continue 2;								// Next foreach iteration

		case 'brokerDown':								// {"cmd":"brokerDown", "id":string}
			if (!isset($message['id']))
				break;
			jMQTT::logger('debug', sprintf('Daemon [%s]: Broker %s disconnected', $ruid, $message['id']));
			jMQTT::on_mqtt_disconnect($message['id']);
			continue 2;								// Next foreach iteration


		case 'daemonUp':							// {"cmd":"daemonUp"}
			jMQTT::logger('debug', sprintf('Daemon [%s]: Python daemon connected successfully to Jeedom', $ruid));
			jMQTT::on_daemon_connect();
			continue 2;								// Next foreach iteration

		case 'daemonDown':							// {"cmd":"daemonDown"}
			jMQTT::logger('debug', sprintf('Daemon [%s]: Python daemon disconnected from Jeedom', $ruid));
			jMQTT::on_daemon_disconnect();
			continue 2;								// Next foreach iteration

		default:
			jMQTT::logger('error', sprintf('Id %d : Received message contains unkown cmd!?', $message['id']));
			continue 2;								// Next foreach iteration
	}

// TODO: Later
/*
	$authorized = jMQTT::valid_uid($ruid);			// Evaluate each time capability of this daemon to send data to Jeedom
	if (!$authorized && ($message['cmd'] != 'daemonUp')) { // Check if daemon is in a correct state or deny the message
		if (is_null($authorized))
			jMQTT::logger('debug', sprintf('Daemon [%s]: Cannot allow cmd "%s" before "daemonUp": "%s".', $ruid, $message['cmd'], json_encode($message)));
		else
			jMQTT::logger('debug', sprintf('Daemon [%s]: Denied msg from invalid Daemon: "%s".', $ruid, json_encode($message)));
		continue;
	}
	switch ($message['cmd']) {						// Handle commands from daemon
		case 'value':								// {"cmd":"value", "c":[string], "v":string} #BrkId, cmdIds, value
			if (!isset($message['c']) || !isset($message['v']))
				break;
			foreach($message['c'] as $cmdId)
				jMQTT::fromDaemon_value($cmdId, $message['v']);
			continue 2;								// Next foreach iteration
		case 'msgIn':								// {"cmd":"msgIn", "b":string, "t":string, "p":string  *(* , "r":int, "r":bool  *)*  }
			if (!isset($message['b']) || !isset($message['t']) || !isset($message['p']))
				break;
			jMQTT::fromDaemon_msgIn($message['b'], $message['t'], $message['p']);
			continue 2;								// Next foreach iteration
		case 'hb':									// {"cmd":"hb"}
			jMQTT::fromDaemon_hb($ruid);
			continue 2;								// Next foreach iteration
		case 'brkUp':								// {"cmd":"brkUp", "brkId":string}
			if (!isset($message['brkId']))
				break;
			jMQTT::fromDaemon_brkUp($message['brkId']);
			continue 2;								// Next foreach iteration
		case 'brkDown':								// {"cmd":"brkDown", "brkId":string}
			if (!isset($message['brkId']))
				break;
			jMQTT::fromDaemon_brkDown($message['brkId']);
			continue 2;								// Next foreach iteration
		case 'daemonUp':							// {"cmd":"daemonUp"}
			echo jMQTT::fromDaemon_daemonUp($ruid);
			continue 2;								// Next foreach iteration
		case 'daemonDown':							// {"cmd":"daemonDown"}
			jMQTT::fromDaemon_daemonDown($ruid);
			continue 2;								// Next foreach iteration
		default:
			jMQTT::logger('error', sprintf('Daemon [%s]: Unkown cmd "%s", see: "%s"', $ruid, $message['cmd'], json_encode($message)));
			continue 2;								// Next foreach iteration
	}
*/
	// All cmds the bad parameters are handled here
	jMQTT::logger('error', sprintf('Daemon [%s]: Missing parameter for cmd "%s", see: "%s".', $ruid, $message['cmd'], json_encode($message)));
}
