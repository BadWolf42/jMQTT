<?php

foreach (jMQTT::byType('jMQTT') as $eqLogic) {
    /** @var jMQTT $eqLogic */

    $coreComment = trim($eqLogic->getComment());
    $confComment = $eqLogic->getConfiguration('commentaire');
    $eqLogic->setConfiguration('commentaire', null);
    if ($coreComment != '') {
        $confComment .= "\n" . $coreComment;
    }
    $eqLogic->setComment($confComment);
    $eqLogic->save();
}
jMQTT::logger('info', __("Commentaires des équipements correctement migrés", __FILE__));


$templateFolderPath = __DIR__ . '/../../core/config/template/';
foreach (ls($templateFolderPath, '*.json', false, array('files', 'quiet')) as $file) {
    try {
        [$templateKey, $templateContent] = jMQTT::templateRead(
            $templateFolderPath . $file
        );

        // Get comment from Core field
        $coreComment = (isset($templateContent['comment'])) ? trim($templateContent['comment']) : '';
        unset($templateContent['comment']);
        // Get comment from config field
        $confComment = $coreComment;
        if (isset($templateContent['configuration']) && isset($templateContent['configuration']['commentaire'])) {
            $confComment = trim($templateContent['configuration']['commentaire']);
            unset($templateContent['configuration']['commentaire']);
        } else {
            // No comment in config, no need to modify this file
            continue;
        }
        // Merge config and Core comment fields
        if ($coreComment != '') {
            $confComment .= "\n" . $coreComment;
        }
        $pos = 0;
        foreach ($templateContent as $tk => $tv) {
            if ($tk == 'configuration')
                break;
            $pos++;
        }
        // Insert comment before configuration
        $templateRes = array_slice($templateContent, 0, $pos, true);
        $templateRes += array('comment' => $confComment);
        $templateRes += array_slice($templateContent, $pos, count($templateContent) - $pos, true);

        // Save back template in the file
        $jsonExport = json_encode(
            array($templateKey => $templateRes),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        file_put_contents($templateFolderPath . $file, $jsonExport . "\n");
    } catch (Throwable $e) {
        jMQTT::logger('error', sprintf(
            __("Erreur lors de la lecture du Template '%s'", __FILE__),
            $file
        ));
    }
}
jMQTT::logger('info', __("Commentaires des Templates correctement migrés", __FILE__));

?>
