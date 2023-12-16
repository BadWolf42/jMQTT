<?php

// list of broker configurations
$configToRemove = array('mqttAddress',
                        'mqttPort',
                        'mqttId',
                        'mqttUser',
                        'mqttPass',
                        'mqttPubStatus',
                        'mqttLwt',
                        'mqttLwtTopic',
                        'mqttLwtOnline',
                        'mqttLwtOffline',
                        'mqttIncTopic',
                        'mqttTls',
                        'mqttTlsCheck',
                        'mqttTlsCaFile',
                        'mqttTlsClient',
                        'mqttTlsClientCertFile',
                        'mqttTlsClientKeyFile',
                        'mqttApi',
                        'mqttApiTopic',
                        'mqttPahoLog',
                        'loglevel');

// getNonBrokers() returns a 2-dimensional array containing eqpt eqLogics
$eqNonBrokers = jMQTT::getNonBrokers();
foreach ($eqNonBrokers as $brk) {
    foreach ($brk as $eqLogic) {

        foreach ($configToRemove as $configKey) {
            // remove leaked configuration
            $eqLogic->setConfiguration($configKey, null);
        }

        $eqLogic->save();
    }
}

jMQTT::logger('info', __("Equipements nettoyés des informations du Broker", __FILE__));


// list of broker configurations
$configToRemove = array('mqttAddress',
                        'mqttPort',
                        'mqttId',
                        'mqttUser',
                        'mqttPass',
                        'mqttPubStatus',
                        'mqttLwt',
                        'mqttLwtTopic',
                        'mqttLwtOnline',
                        'mqttLwtOffline',
                        'mqttIncTopic',
                        'mqttTls',
                        'mqttTlsCheck',
                        'mqttTlsCaFile',
                        'mqttTlsClient',
                        'mqttTlsClientCertFile',
                        'mqttTlsClientKeyFile',
                        'mqttApi',
                        'mqttApiTopic',
                        'mqttPahoLog',
                        'loglevel');
$templateFolderPath = __DIR__ . '/../../data/template';
foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
    try {
        $content = file_get_contents($templateFolderPath . '/' . $file);
        if (is_json($content)) {
            // decode template file content to json
            $templateContent = json_decode($content, true);
            // first key is the template itself
            $templateKey = array_keys($templateContent)[0];
            // if 'configuration' key exists in this template
            if (isset($templateContent[$templateKey]['configuration'])) {
                // for each keys under 'configuration'
                foreach (array_keys($templateContent[$templateKey]['configuration']) as $configurationKey) {
                    // if this configurationKey is in keys to remove
                    if (in_array($configurationKey, $configToRemove)) {
                        // remove it
                        unset($templateContent[$templateKey]['configuration'][$configurationKey]);
                    }
                }
            }
            // Save back template in the file
            $jsonExport = json_encode($templateContent, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents($templateFolderPath . '/' . $file, $jsonExport);
        }
    } catch (Throwable $e) {}
}

jMQTT::logger('info', __("Templates nettoyés des informations du Broker", __FILE__));

?>
