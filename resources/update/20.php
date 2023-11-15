<?php

// Delete orphan crons
/** @var null|false|object $cron */
// @phpstan-ignore-next-line
while ($cron = cron::byClassAndFunction('jMQTT', 'disableIncludeMode')) {
    $cron->remove(false);
}

jMQTT::logger('info', __("Crons orphelins supprimés", __FILE__));

?>
