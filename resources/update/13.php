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
            jMQTT::logger('error', sprintf("%1\$s() has raised Exception: %2\$s", 'update_13_0_0', $e->getMessage()));
        else
            jMQTT::logger(
                'error',
                str_replace(
                    "\n",
                    ' <br/> ',
                    sprintf(
                        "%1\$s() has raised Exception: %2\$s,<br/>@Stack: %3\$s,<br/>@BrokerId: %4\$s.",
                        'update_13_0_0',
                        $e->getMessage(),
                        $e->getTraceAsString(),
                        $broker->getId()
                    )
                )
            );
    }
}

jMQTT::logger('info', "Configuring the updated Client-Id");

?>
