<?php

$oldPath = realpath(__DIR__ . '/../../data/jmqtt/certs');

// for each brokers
foreach ((jMQTT::getBrokers()) as $broker) {
    $broker->setConfiguration(
        'mqttProto',
        boolval($broker->getConfiguration('mqttTls', 0)) ? 'mqtts' : 'mqtt'
    );
    $broker->setConfiguration('mqttTls', null);
    $cert = $broker->getConfiguration('mqttTlsCaFile', '');
    $broker->setConfiguration('mqttTlsCaFile', null);
    if ($cert != '')
        $broker->setConfiguration(
            'mqttTlsCa', file_get_contents($oldPath.'/'.$cert)
        );
    $cert = $broker->getConfiguration('mqttTlsClientCertFile', '');
    $broker->setConfiguration('mqttTlsClientCertFile', null);
    if ($cert != '')
        $broker->setConfiguration(
            'mqttTlsClientCert', file_get_contents($oldPath.'/'.$cert)
        );
    $cert = $broker->getConfiguration('mqttTlsClientKeyFile', '');
    $broker->setConfiguration('mqttTlsClientKeyFile', null);
    if ($cert != '')
        $broker->setConfiguration(
            'mqttTlsClientKey', file_get_contents($oldPath.'/'.$cert)
        );
    $broker->save();
}

// rm -rf $oldPath
foreach (array_diff(scandir($oldPath), array('.','..')) as $file) {
    unlink($oldPath.'/'.$file);
}

rmdir($oldPath);

jMQTT::logger('info', __("Certificats déplacés dans la base de donnée", __FILE__));

?>
