<?php

// Delete orphan crons
/** @var null|object $cron */
while ($cron = cron::byClassAndFunction('jMQTT', 'disableIncludeMode')) {
    $cron->remove(false);
}

jMQTT::logger('info', __("Crons orphelins supprimés", __FILE__));

?>
