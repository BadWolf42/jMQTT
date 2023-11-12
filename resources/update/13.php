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
        if (log::getLogLevel(jMQTT::class) > 100)
            jMQTT::logger('error', sprintf(__("%1\$s() a levé l'Exception: %2\$s", __FILE__), __FUNCTION__, $e->getMessage()));
        else
            jMQTT::logger(
                'error',
                str_replace(
                    "\n",
                    ' <br/> ',
                    sprintf(
                        __("%1\$s() a levé l'Exception: %2\$s", __FILE__).
                        ",<br/>@Stack: %3\$s,<br/>@BrokerId: %4\$s.",
                        __FUNCTION__,
                        $e->getMessage(),
                        $e->getTraceAsString(),
                        $broker->getId()
                    )
                )
            );
    }
}

jMQTT::logger('info', __("Configuration des Client-Id mises à jour", __FILE__));

?>
