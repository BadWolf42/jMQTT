<?php
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

require_once dirname(__FILE__) . '/../../3rdparty/vendor/autoload.php';
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

$options = getopt('',array('plugin:','socketport:','pid:'));
if (array_key_exists('plugin', $options)) {
    $plugin = $options['plugin'];
}
else {
    echo("[ERROR] !!! plugin name / class not provided !!! => Exit\n");
    return;
}
if (array_key_exists('pid', $options)) {
    $pidfile = $options['pid'];
}
else {
    log::add($plugin, 'error', 'pidfile path not provided => Exit');
    return;
}
if (array_key_exists('socketport', $options)) {
    $socketport = intval($options['socketport']);
}
else {
    log::add($plugin, 'error', 'socketport not provided => Exit');
    return;
}

log::add($plugin, 'info', 'Start jMQTT websocket daemon');
log::add($plugin, 'info', 'Plugin : ' . $plugin);
log::add($plugin, 'info', 'Socket port : ' . $socketport);
log::add($plugin, 'info', 'PID file : ' . $pidfile);

//Check for daemon already running
if (file_exists($pidfile)) {
    log::add($plugin, 'debug', 'PID File "' . $pidfile . '" already exists.');
    log::add($plugin, 'error', 'This daemon already runs! Exit 0');
    exit(0);
}

// Create pid file
$pid = posix_getpid();
log::add($plugin, 'debug', 'Writing PID ' . $pid . ' to ' . $pidfile);
$fp = fopen($pidfile, 'x');
if($fp == false){
    log::add($plugin, 'error', 'Can\'t open pidfile => Exit');
    return;
}
fwrite($fp, $pid);
fclose($fp);


class jMQTTdLogic implements MessageComponentInterface {

    private $plugin;

    public function __construct(string $plugin) {
        $this->plugin = $plugin;
    }

    public function onOpen(ConnectionInterface $conn) {

        if ( ! $conn->httpRequest->hasHeader('id') || ! $conn->httpRequest->hasHeader('apikey')) {
            $conn->send('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
            $conn->close();
        }

        if (!jeedom::apiAccess($conn->httpRequest->getHeader('apikey')[0], $this->plugin)) {
            $conn->send('Vous n\'etes pas autorisé à effectuer cette action', __FILE__);
            $conn->close();
        }

        log::add($this->plugin, 'debug', sprintf('Id %d : Python daemon connected successfully to WebSocket Daemon', $conn->httpRequest->getHeader('id')[0]));

        if(method_exists($this->plugin, 'on_daemon_connect')) {
            $this->plugin::on_daemon_connect($conn->httpRequest->getHeader('id')[0]);
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {

        log::add($this->plugin, 'debug', sprintf('Id %d : onMessage received : "%s"', $from->httpRequest->getHeader('id')[0], $msg));

        $message = json_decode($msg, true);
        
        if ($message == null) {
            log::add($this->plugin, 'error', sprintf('Received message is not a correct JSON!? : %s', $msg));
            return;
        }
        if (!array_key_exists('cmd', $message)) {
            log::add($this->plugin, 'error', sprintf('Received message doesn\'t contain cmd!? : %s', $msg));
            return;
        }

        switch ($message['cmd']) {
            case 'connection':
                if ($message['state']) {
                    if(method_exists($this->plugin, 'on_mqtt_connect')) {
                        $this->plugin::on_mqtt_connect($from->httpRequest->getHeader('id')[0]);
                    }
                }
                else {
                    if(method_exists($this->plugin, 'on_mqtt_disconnect')) {
                        $this->plugin::on_mqtt_disconnect($from->httpRequest->getHeader('id')[0]);
                    }
                }
                break;
            case 'messageIn':
                if(method_exists($this->plugin, 'on_mqtt_message')) {
                    $this->plugin::on_mqtt_message($from->httpRequest->getHeader('id')[0], $message['topic'], $message['payload'], $message['qos'], $message['retain']);
                }
                break;
            default:
                log::add($this->plugin, 'error', sprintf('Id %d : Received message contains unkown cmd!?', $from->httpRequest->getHeader('id')[0]));
                break;
        }
    }

    public function onClose(ConnectionInterface $conn) {
        log::add($this->plugin, 'debug', sprintf('Id %d : Python daemon disconnected from WebSocket Daemon', $conn->httpRequest->getHeader('id')[0]));

        if(method_exists($this->plugin, 'on_daemon_disconnect')) {
            $this->plugin::on_daemon_disconnect($conn->httpRequest->getHeader('id')[0]);
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        log::add($this->plugin, 'error', sprintf('Id %d : Unexpected error between WebSocket Daemon and Python Daemon', $conn->httpRequest->getHeader('id')[0], $e->getMessage()));

        $conn->close();
    }
}

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new jMQTTdLogic($plugin)
        )
    ),
    $socketport
);

function shutdown() {
    global $plugin, $pidfile, $server;
    log::add($plugin, 'debug', 'Shutdown');
    log::add($plugin, 'debug', 'Removing PID file ' . $pidfile);
    $server->loop->stop();
    unlink($pidfile);
}

pcntl_signal(SIGTERM, 'shutdown');
pcntl_signal(SIGINT, 'shutdown');

// if PHP 7.1.0 or above
if (PHP_MAJOR_VERSION >= 8 || (PHP_MAJOR_VERSION == 7 && PHP_MINOR_VERSION >= 1)) {
    //Enable the new async signal handling
    pcntl_async_signals(TRUE);
}
else { // older PHP version
    //Use the older manual dispatch in $server->loop
    $server->loop->addPeriodicTimer(0.25,function(){ pcntl_signal_dispatch(); });
}

log::add($plugin, 'debug', 'Listening on: [127.0.0.1:' . $socketport . ']');
$server->run();

log::add($plugin, 'debug', 'Exit 0');