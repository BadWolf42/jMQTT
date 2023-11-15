<?php

// for each Broker
foreach ((jMQTT::getBrokers()) as $broker) {
    try {
        // Set 'mqttLwt' config only if not already set
        if ($broker->getConfiguration(jMQTTConst::CONF_KEY_MQTT_LWT, 'NotThere') == 'NotThere') {
            // copy 'mqttPubStatus' value to 'mqttLwt' in broker config
            $broker->setConfiguration(jMQTTConst::CONF_KEY_MQTT_LWT, $broker->getConfiguration('mqttPubStatus', '0'));
        }
        // delete 'mqttPubStatus' from broker config
        $broker->setConfiguration('mqttPubStatus', null);

        // Set 'mqttApi' config only if not already set
        if ($broker->getConfiguration(jMQTTConst::CONF_KEY_MQTT_API, 'NotThere') == 'NotThere') {
            // copy 'api' value to 'mqttApi' in broker config
            $broker->setConfiguration(jMQTTConst::CONF_KEY_MQTT_API, ($broker->getConfiguration('api', '0') == 'enable') ? '1' : '0');
        }
        // delete 'api' from broker config
        $broker->setConfiguration('api', null);

        // remove old include_mode cache key
        $broker->setCache('include_mode', null);

        $broker->save();
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

foreach (jMQTT::byType(jMQTT::class) as $eqLogic) {
    /** @var jMQTTCmd $eqLogic */
    try {
        if ($eqLogic->getType() == jMQTTConst::TYP_BRK) {
            jMQTT::logger('debug', $eqLogic->getHumanName() . ' est un Broker');
            continue;
        }
        // Set 'eqLogic' config only if not already set
        if ($eqLogic->getConfiguration(jMQTTConst::CONF_KEY_BRK_ID, 'NotThere') == 'NotThere') {
            // copy 'brkId' value to 'eqLogic' in broker config
            $eqLogic->setConfiguration(jMQTTConst::CONF_KEY_BRK_ID, $eqLogic->getConfiguration('brkId', -1));
            // delete 'brkId' from broker config
            $eqLogic->setConfiguration('brkId', null);
            $eqLogic->save(true); // Direct save to avoid issues while saving
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
                        ",<br/>@Stack: %3\$s,<br/>@EqlogicId: %4\$s.",
                        __FUNCTION__,
                        $e->getMessage(),
                        $e->getTraceAsString(),
                        $eqLogic->getId()
                    )
                )
            );
    }
}

jMQTT::logger('info', __("Clés de configuration des Brokers jMQTT modifiées", __FILE__));

?>
