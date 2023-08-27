<?php

// Delete orphan crons
while ($cron = cron::byClassAndFunction('jMQTT', 'disableIncludeMode')) {
    $cron->remove(false);
}

jMQTT::logger('info', __("Crons orphelins supprimés", __FILE__));

?>
