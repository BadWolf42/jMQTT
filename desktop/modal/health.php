<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

if (!isConnect('admin')) {
    throw new Exception('401 Unauthorized');
}
/** @var jMQTT[][] $eqNonBrokers */
$eqNonBrokers = jMQTT::getNonBrokers();
/** @var jMQTT[] $eqBrokers */
$eqBrokers = jMQTT::getBrokers();

function getStatusHtml($status) {
    switch ($status) {
        case 'ok':
            return '<span class="label label-success" style="font-size : 1em; cursor : default;">{{OK}}</span>';
        case 'pok':
            return '<span class="label label-warning" style="font-size : 1em; cursor : default;">{{POK}}</span>';
        case 'nok':
            return '<span class="label label-danger" style="font-size : 1em; cursor : default;">{{NOK}}</span>';
    }
}

function getIsEnableHtml($eqL) {
    if ($eqL->getIsEnable()) {
        return getStatusHtml('ok');
    }
    else {
        return getStatusHtml('nok');
    }
}

echo '<legend><i class="fas fa-table"></i> {{Brokers}}</legend>';
echo '<table class="table table-condensed tablesorter" id="table_healthMQTT">';
echo '<thead><tr><th>{{Broker}}</th><th>{{ID}}</th><th>{{Statut}}</th><th>{{Dernière communication}}</th><th>{{Date création}}</th></tr></thead><tbody>';
foreach ($eqBrokers as $eqB) {
    $info = $eqB->getDaemonInfo();
    echo '<tr><td><a href="' . $eqB->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqB->getHumanName(true) . '</a></td>';
    echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqB->getId() . '</span></td>';
    echo '<td>' . getStatusHtml($info['state']) . ' ' . $info['message'] . '</td>';
    echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqB->getStatus('lastCommunication') . '</span></td>';
    echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqB->getConfiguration('createtime') . '</span></td></tr>';
}
echo '</tbody></table>';

foreach ($eqBrokers as $eqB) {
    echo '<legend><i class="fas fa-table"></i> {{Equipements connectés à}} <b>' . $eqB->getName() . '</b></legend>';
    echo '<table class="table table-condensed tablesorter" id="table_healthMQTT">';
    echo '<thead><tr><th>{{Module}}</th><th>{{ID}}</th><th>{{Activé}}</th><th>{{Inscrit au Topic}}</th><th>{{Dernière communication}}</th><th>{{Date création}}</th></tr></thead><tbody>';
    if (array_key_exists($eqB->getId(), $eqNonBrokers)) {
        foreach ($eqNonBrokers[$eqB->getId()] as $eqL) {
            echo '<tr><td><a href="' . $eqL->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqL->getHumanName(true) . '</a></td>';
            echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqL->getId() . '</span></td>';
            echo '<td>' . getIsEnableHtml($eqL) . '</td>';
            echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqL->getLogicalId() . '</span></td>';
            echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqL->getStatus('lastCommunication') . '</span></td>';
            echo '<td><span class="label label-info" style="font-size : 1em; cursor : default;">' . $eqL->getConfiguration('createtime') . '</span></td></tr>';
        }
    }
    echo '</tbody></table>';
}
