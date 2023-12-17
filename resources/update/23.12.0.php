<?php

// Delete old daemon folders
exec(system::getCmdSudo() . 'rm -rf '.__DIR__.'/../../resources/jmqttd');
exec(system::getCmdSudo() . 'rm -rf '.__DIR__.'/../../resources/python-requirements');

jMQTT::logger('info', __("Ancien démon supprimé", __FILE__));

?>
