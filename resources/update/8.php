<?php


$eqLogics = jMQTT::byType('jMQTT');
/** @var jMQTT[] $eqLogics */
foreach ($eqLogics as $eqLogic) {
    // Detect presence of auto_add_topic
    $keyPresence = $eqLogic->getConfiguration('auto_add_topic', 'ThereIsNoKeyHere');
    if ($keyPresence == 'ThereIsNoKeyHere') {
        $eqLogic->setTopic($eqLogic->getLogicalId());
        $eqLogic->setLogicalId('');
        // Direct save to avoid daemon notification and Exception if daemon is not Up
        $eqLogic->save(true);
    }
}

jMQTT::logger(
    'info',
    __("Topics déplacé vers la configuration pour tous les équipements jMQTT", __FILE__)
);


$templateFolderPath = __DIR__ . '/../../data/template';
foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $_filename) {
    try {
        [$templateKey, $templateValue] = jMQTT::templateRead(
            __DIR__ . '/../../data/template/' . $_filename
        );
        // if 'configuration' key exists in this template
        if (isset($templateValue['configuration'])) {
            // if auto_add_cmd doesn't exists in configuration,
            // we need to move topic from logicalId to configuration
            if (!isset($templateValue['configuration']['auto_add_topic'])) {
                $topic = $templateValue['logicalId'];
                $templateValue['configuration']['auto_add_topic'] = $topic;
                $templateValue['logicalId'] = '';
            }
        }

        // Save back template in the file
        $jsonExport = json_encode(
            array($templateKey=>$templateValue),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        file_put_contents(
            __DIR__ . '/../../data/template/' . $_filename,
            $jsonExport
        );
    } catch (Throwable $e) {
        throw new Exception(
            sprintf(
                __("Erreur lors de la lecture du Template '%s'", __FILE__), $_filename
            )
        );
    }
}

jMQTT::logger(
    'info',
    __("Topics déplacé vers la configuration pour tous les Templates jMQTT", __FILE__)
);

?>
