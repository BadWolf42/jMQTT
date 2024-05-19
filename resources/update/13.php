<?php

// for each Broker
foreach ((jMQTT::getBrokers()) as $broker) {
    try {
        // Set 'mqttId' & 'mqttIdValue' config, only if 'mqttIdValue' config not already set
        if ($broker->getConfiguration('mqttIdValue', 'NotThere') == 'NotThere') {
            $mqttIdValue = $broker->getConfiguration('mqttId', '');
            // Copy 'mqttId' into 'mqttIdValue'
            $broker->setConfiguration('mqttIdValue', $mqttIdValue);

            // Set 'mqttId' to '1' if 'mqttIdValue' is set, '0' otherwise
            $broker->setConfiguration('mqttId', ''.intval($mqttIdValue != ''));

            // Save eqBroker if modified
            $broker->save();
        }
    } catch (Throwable $e) {
        if (log::getLogLevel(jMQTT::class) > 100) {
            jMQTT::logger('error', sprintf("%s raised Exception: %s", __FILE__, $e->getMessage()));
        } else {
            jMQTT::logger('error', sprintf(
                "%s raised Exception: %s\n@Stack: %s\n@BrokerId: %s",
                __FILE__,
                $e->getMessage(),
                $e->getTraceAsString(),
                $broker->getId()
            ));
        }
    }
}

jMQTT::logger('info', __("Configuration des Client-Id mises à jour", __FILE__));

?>
