<?php

foreach (jMQTT::byType('jMQTT') as $eqLogic) {
    /** @var jMQTT $eqLogic */
    try {
        cache::delete('jMQTT::' . $eqLogic->getId() . '::ignore_topic_mismatch');
    } catch (Throwable $e) {
        jMQTT::logger('error', sprintf(
            __("Erreur lors du nettoyage du cache de l'équipement #%s#", __FILE__),
            $eqLogic->getHumanName()
        ));
    }
}

jMQTT::logger('info', __("Cache des équipements correctement nettoyés", __FILE__));
