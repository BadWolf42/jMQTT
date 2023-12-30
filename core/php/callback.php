<?php

require_once __DIR__ . '/../../../../core/php/core.inc.php';


class JmqttdCallbacks {
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

    public static function checkAuthorization() {
        $headers = getallheaders();
        if (
            !array_key_exists('Authorization', $headers)
            || 'Bearer' !== trim(substr($headers['Authorization'], 0, 6))
        ) {
            http_response_code(401);
            echo 'Unauthorized access.';
            log::add(
                'jMQTT',
                'error',
                sprintf(
                    __("Accès non autorisé depuis %1\$s (pas de clé API)", __FILE__),
                    $_SERVER['REMOTE_ADDR']
                )
            );
            die();
        }

        if (!jeedom::apiAccess(trim(substr($headers['Authorization'], 6)), 'jMQTT')) {
            http_response_code(401);
            echo 'Unauthorized access.';
            log::add(
                'jMQTT',
                'error',
                sprintf(
                    __("Accès non autorisé depuis %1\$s, avec la clé API commençant par %2\$.8s...", __FILE__),
                    $_SERVER['REMOTE_ADDR'],
                    trim(substr($headers['Authorization'], 6))
                )
            );
        }
    }

    public static function getPayload() {
        try {
            $received = file_get_contents("php://input"); // Get page full content
            jMQTT::logger('debug', sprintf("Payload='%1\$s'", $received));
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
            $message = json_decode($received, true, 128, JSON_THROW_ON_ERROR ); // Try to decode json
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
        if (!is_callable(array(self::class, 'on'.ucfirst($action)))) {
            http_response_code(501);
            echo 'Not Implemented.';
            die();
        }
        return $action;
    }

    public static function processRequest() {
        // Check if API key and method are correct (or die)
        self::checkAuthorization();

        // NOT POST, we just close the connection
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed.';
            die();
        }

        // Get action and remote uid (or die)
        $action = self::getAction();

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
        $id = $message['id'];
        jMQTT::logger('debug', sprintf("%1\$s: %2\$s", __METHOD__, $id));
        // jMQTTComFromDaemon::brkDown($id);
    }

    public static function onBrokerUp() {
        $message = self::getPayload();
        if (!isset($message['id'])) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        $id = $message['id'];
        jMQTT::logger('debug', sprintf("%1\$s: %2\$s", __METHOD__, $id));
        // jMQTTComFromDaemon::brkUp($message['id']);
    }

    public static function onDaemonDown() {
        jMQTT::logger('debug', __METHOD__);
        jMQTTComFromDaemon::daemonDown();
    }

    public static function onDaemonHB() {
        jMQTT::logger('debug', __METHOD__);
        jMQTTComFromDaemon::hb();
    }

    public static function onDaemonUp() {
        $message = self::getPayload();
        if (!isset($message['port'])) {
            http_response_code(400);
            echo 'Bad Request parameter.';
            die();
        }
        jMQTT::logger('debug', __METHOD__);
        jMQTTComFromDaemon::daemonUp($message['port']);
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
                "onMessage: id='%1\$s', topic='%2\$s', payload='%3\$s', qos=%4\$s, retain=%5\$s",
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
        jMQTT::logger('debug', __METHOD__);
    }

    public static function onValues() {
        $message = self::getPayload();
        if (!is_array($message)) {
            echo 'Bad Request.';
            die();
        }
        foreach ($message as $val) {
            jMQTT::logger(
                'debug',
                sprintf(
                    "onValues: id='%1\$s', value='%2\$s'",
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
        jMQTT::logger('debug', sprintf("%1\$s: %2\$s", __METHOD__, $message['id']));
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
                "%1\$s: %2\$s / %3\$s",
                __METHOD__,
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
