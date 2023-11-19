<?php


foreach (jMQTT::byType('jMQTT') as $eqLogic) {
    /** @var jMQTT $eqLogic */
    // Protect already modified Eq
    $batId = $eqLogic->getBatteryCmd();
    if (
        $batId != false
        && $batId != '' // @phpstan-ignore-line
    ) {
        $cmd = jMQTTCmd::byId($batId);
        jMQTT::logger('info', sprintf(__("#%1\$s# définit DÉJÀ la batterie de #%2\$s#", __FILE__), $cmd->getHumanName(), $eqLogic->getHumanName()));
        continue;
    }
    // get info cmds of current eqLogic
    foreach (jMQTTCmd::byEqLogicId($eqLogic->getId(), 'info') as $cmd) {
        /** @var jMQTTCmd $cmd */
        // Old isBattery()
        if ($cmd->getType() == 'info' && ($cmd->getGeneric_type() == 'BATTERY' || preg_match('/(battery|batterie)$/i', $cmd->getName()))) {
            $eqLogic->setConfiguration(jMQTTConst::CONF_KEY_BATTERY_CMD, $cmd->getId());
            jMQTT::logger('info', sprintf(__("#%1\$s# définit la batterie de #%2\$s#", __FILE__), $cmd->getHumanName(), $eqLogic->getHumanName()));
            $eqLogic->save();
        }
    }
}

jMQTT::logger('info', __("Commandes batterie définies directement sur les équipements jMQTT", __FILE__));

?>
