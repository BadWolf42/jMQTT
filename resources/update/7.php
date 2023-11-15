<?php

$eqLogics = jMQTT::byType('jMQTT');
foreach ($eqLogics as $eqLogic) {
    // get info cmds of current eqLogic
    /** @var jMQTTCmd[] $infoCmds */
    $infoCmds = jMQTTCmd::byEqLogicId($eqLogic->getId(), 'info');
    // split topic and jsonPath of cmd
    foreach ($infoCmds as $cmd) {
        // Try to find '{'
        $topic = $cmd->getTopic();
        $i = strpos($topic, '{');
        // If no '{'
        if ($i === false) {
            // Just set empty jsonPath if it doesn't exists
            $cmd->setJsonPath($cmd->getJsonPath());
        } else {
            // Set cleaned Topic
            $cmd->setTopic(substr($topic, 0, $i));

            // Split old json path
            $indexes = substr($topic, $i);
            $indexes = str_replace(array('}{', '{', '}'), array('|', '', ''), $indexes);
            $indexes = explode('|', $indexes);

            $jsonPath = '';
            // For each part of the path
            foreach ($indexes as $index) {
                // if this part contains a dot, a space or a slash, escape it
                if (strpos($index, '.') !== false || strpos($index, ' ') !== false || strpos($index, '/') !== false)
                    $jsonPath .= '[\'' . $index . '\']';
                else
                    $jsonPath .= '[' . $index . ']';
            }

            $cmd->setJsonPath($jsonPath);
        }
        $cmd->save(true); // Direct save to avoid pre/postSave
    }
}

jMQTT::logger('info', __("JsonPath séparé du Topic pour tous les commandes info jMQTT", __FILE__));


$templateFolderPath = __DIR__ . '/../../data/template';
foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
    try {
        [$templateKey, $templateValue] = jMQTT::templateRead(
            __DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO . $file
        );

        // Keep track of any change
        $changed = false;

        // if 'commands' key exists in this template
        if (isset($templateValue['commands'])) {

            // for each keys under 'commands'
            foreach ($templateValue['commands'] as &$cmd) {

                // if 'configuration' key exists in this command
                if (isset($cmd['configuration'])) {

                    // get the topic if it exists
                    $topic = (isset($cmd['configuration']['topic'])) ? $cmd['configuration']['topic'] : '';

                    $i = strpos($topic, '{');
                    if ($i === false) {
                        // Just set empty jsonPath if it doesn't exists
                        if (!isset($cmd['configuration'][jMQTTConst::CONF_KEY_JSON_PATH])) {
                            $cmd['configuration'][jMQTTConst::CONF_KEY_JSON_PATH] = '';
                            $changed = true;
                        }
                    } else {
                        $changed = true;
                        // Set cleaned Topic
                        $cmd['configuration']['topic'] = substr($topic, 0, $i);

                        // Split old json path
                        $indexes = substr($topic, $i);
                        $indexes = str_replace(array('}{', '{', '}'), array('|', '', ''), $indexes);
                        $indexes = explode('|', $indexes);

                        $jsonPath = '';
                        // For each part of the path
                        foreach ($indexes as $index) {
                            // if this part contains a special character, escape it
                            if (preg_match('/[^\w-]/', $index) !== false)
                                $jsonPath .= '[\'' . str_replace("'", "\\'", $index) . '\']';
                            else
                                $jsonPath .= '[' . $index . ']';
                        }
                        $cmd['configuration'][jMQTTConst::CONF_KEY_JSON_PATH] = $jsonPath;
                    }
                }
            }
        }

        // Don't write anything if no change was made
        if (!$changed)
            return;

        // Save back template in the file
        $jsonExport = json_encode(array($templateKey=>$templateValue), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        file_put_contents(__DIR__ . '/../../' . jMQTTConst::PATH_TEMPLATES_PERSO . $file, $jsonExport);
    } catch (Throwable $e) {
        throw new Exception(sprintf(__("Erreur lors de la lecture du Template '%s'", __FILE__), $file));
    }
}

jMQTT::logger('info', __("JsonPath séparé du Topic pour tous les Templates jMQTT", __FILE__));


raiseForceDepInstallFlag();

?>
