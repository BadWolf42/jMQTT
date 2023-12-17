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



class JmqttdCallbacks {
    private static $ruid = '0:0';
    private static $allowedActions = array(
        'brokerDown',
        'brokerUp',
        'daemonDown',
        'daemonHB',
        'daemonUp',
        'message',
        'test',
        'values'
    );

    public static function checkApiKey() {
        if (!jeedom::apiAccess(init('k'), 'jMQTT')) {
        // if (init('k') !== '!secret') {                     // TODO Remove me
            http_response_code(401);
            echo 'Unauthorized access.';
            if (init('k') != '')
                log::add(
                    'jMQTT',
                    'error',
                    sprintf(
                        __("Accès non autorisé depuis %1\$s, avec la clé API commençant par %2\$.8s...", __FILE__),
                        $_SERVER['REMOTE_ADDR'],
                        init('key')
                    )
                );
            else
                log::add(
                    'jMQTT',
                    'error',
                    sprintf(
                        __("Accès non autorisé depuis %s (pas de clé API)", __FILE__),
                        $_SERVER['REMOTE_ADDR']
                    )
                );
            die();
        }
    }

    public static function checkMethod() {
        // NOT POST, we just close the connection
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed.';
            die();
        }
    }

    public static function getRuid() {
        // Collect Remote PID and PORT at connection
        return init('u');
    }

    public static function getPayload() {
        try {
            $received = file_get_contents("php://input"); // Get page full content
            jMQTT::logger(
                'debug',
                sprintf(
                    __("Payload='%1\$s'", __FILE__),
                    $received
                )
            );
        } catch (Throwable $e) {
            http_response_code(400);
            echo 'Bad Request.';
            die();
        }
        if (empty($received)) {
            http_response_code(406);
            echo 'Not Acceptable Content.';
            die();
        }
        try {
            $message = json_decode($received, true); // Try to decode json
        } catch (Throwable $e) {
            jMQTT::logger(
                'error',
                sprintf(
                    __("Format JSON erroné: '%1\$s'", __FILE__),
                    $received
                )
            );
            http_response_code(406);
            echo 'Not Acceptable Value.';
            die();
        }
        return $message;
    }

    public static function getAction() {
        $action = init('a'); // Collect Requested action
        if (!in_array($action, self::$allowedActions, true)) {
            http_response_code(406);
            echo 'Not Acceptable Action.';
            die();
        }
        if (!is_callable(self::class, 'on'.ucfirst($action))) {
            http_response_code(501);
            echo 'Not Implemented.';
            die();
        }
        return $action;
    }

    public static function processRequest() {
        // Check if API key and method are correct (or die)
        self::checkApiKey();
        self::checkMethod();

        // Get action and remote uid (or die)
        $action = self::getAction();
        self::$ruid = self::getRuid();

        // Evaluate the capability of this daemon to send data to Jeedom
        $legit = true;
        // $legit = jMQTTDaemon::valid_uid($ruid);

        // Check if daemon is in a correct state or deny the message
        if (!$legit && ($action != 'daemonUp') && $action != 'test') {
            jMQTT::logger(
                'debug',
                sprintf(
                    is_null($legit) ?
                    __("Requête '%1\$s' refusée (avant daemonUp)", __FILE__) :
                    __("Requête '%1\$s' refusée (démon invalide)", __FILE__),
                    $action
                )
            );
            http_response_code(406);
            echo 'Not Acceptable Demand.';
            die();
        }

        // Execute static method corresponding to the action
        $action = 'on'.ucfirst($action);
        self::$action();

        // Finally return 200
        http_response_code(200);
        die();
    }


    public static function onBrokerDown() {
        $message = self::getPayload();
        if (!isset($message['id'])) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        jMQTT::logger(
            'debug',
            sprintf(
                __("brokerDown: %1\$s", __FILE__),
                $message['id']
            )
        );
        // jMQTTComFromDaemon::brkDown($message['id']);
    }

    public static function onBrokerUp() {
        $message = self::getPayload();
        if (!isset($message['id'])) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        jMQTT::logger(
            'debug',
            sprintf(
                __("brokerUp: %1\$s", __FILE__),
                $message['id']
            )
        );
        // jMQTTComFromDaemon::brkUp($message['id']);
    }

    public static function onDaemonDown() {
        jMQTT::logger(
            'debug',
            sprintf(__("daemonDown", __FILE__))
        );
        // jMQTTComFromDaemon::daemonDown($ruid);
    }

    public static function onDaemonHB() {
        jMQTT::logger(
            'debug',
            sprintf(__("daemonHB", __FILE__))
        );
        // jMQTTComFromDaemon::hb($ruid);
    }

    public static function onDaemonUp() {
        jMQTT::logger(
            'debug',
            sprintf(__("daemonUp", __FILE__))
        );
        // jMQTTComFromDaemon::daemonUp($ruid);
    }

    public static function onMessage() {
        $message = self::getPayload();
        if (
            !isset($message['id'])
            || !isset($message['topic'])
            || !isset($message['payload'])
        ) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        jMQTT::logger(
            'debug',
            sprintf(
                __("message: id='%1\$s', topic='%2\$s', payload='%3\$s', qos=%4\$s, retain=%5\$s", __FILE__),
                $message['id'],
                $message['topic'],
                $message['payload'],
                $message['qos'],
                $message['retain'] ? 'True' : 'False'
            )
        );
        // jMQTTComFromDaemon::msgIn($message['id'], $message['topic'], $message['payload'], $message['qos'], $message['retain']);
    }

    public static function onTest() {
        jMQTT::logger(
            'debug',
            sprintf(__("test", __FILE__))
        );
    }

    public static function onValues() {
        $message = self::getPayload();
        if (!is_array($message)) {
            echo 'Bad Request.';
            die();
        }
        foreach($message as $val) {
            jMQTT::logger(
                'debug',
                sprintf(
                    __("values: id='%1\$s', value='%2\$s'", __FILE__),
                    $val['id'],
                    $val['value']
                )
            );
            // jMQTTComFromDaemon::value($val['id'], $val['value']);
        }
    }

/*
    public static function onRealTimeStarted() {
        $message = self::getPayload();
        if (!isset($message['id'])) {
            echo 'Bad Request.';
            die();
        }
        jMQTT::logger(
            'debug',
            sprintf(
                __("realTimeStarted: %1\$s", __FILE__),
                $message['id']
            )
        );
        // jMQTTComFromDaemon::realTimeStarted($message['id']);
    }

    public static function onRealTimeStopped() {
        $message = self::getPayload();
        if (!isset($message['id']) || !isset($message['nbMsgs'])) {
            echo 'Bad Request.';
            die();
        }
        jMQTT::logger(
            'debug',
            sprintf(
                __("realTimeStopped: %1\$s / %2\$s", __FILE__),
                $message['id'],
                $message['nbMsgs']
            )
        );
        // jMQTTComFromDaemon::realTimeStopped($message['id'], $message['nbMsgs']);
    }
*/

}

// Call the request processor
JmqttdCallbacks::processRequest();
