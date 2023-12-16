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
    throw new Exception('{{401 - Accès non autorisé}}');
}
/** @var jMQTT[][] $eqNonBrokers */
$eqNonBrokers = jMQTT::getNonBrokers();
/** @var jMQTT[] $eqBrokers */
$eqBrokers = jMQTT::getBrokers();

// TODO: Move orphan eqLogic & cmd search and rescue in health modal
//  labels: enhancement, php

?>
<legend><i class="fas fa-table"></i> {{Brokers}}</legend>
<table class="table table-condensed tablesorter" id="table_healthMQTT_brk">
    <thead>
        <tr>
            <th class="col-md-3">{{Broker}}</th>
            <th class="col-md-1 center">{{ID}}</th>
            <th class="col-md-4">{{Statut}}</th>
            <th class="col-md-1 center">{{Équipements}}</th>
            <th class="col-md-1 center">{{Dernière comm.}}</th>
            <th class="col-md-1 center">{{Date de création}}</th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    <tbody>
<?php
foreach ($eqBrokers as $eqB) { // List all Brokers on top
    echo '<tr><td><a href="' . $eqB->getLinkToConfiguration() . '" class="eName" data-key="' . $eqB->getHumanName() . '" style="text-decoration: none;">' . $eqB->getHumanName(true) . '</a></td>';
    echo '<td style="text-align:center"><span class="label label-info eId" style="font-size:1em;cursor:default;width:70px;height:20px;">' . $eqB->getId() . '</span></td>';
    echo '<td>' . $eqB->getMqttClientInfo()['message'] . '</td>';
    echo '<td style="text-align:center"><span class="label label-info" style="font-size:1em;cursor:default;width:60px;height:20px;">' . count($eqNonBrokers[$eqB->getId()]) . '</span></td>';
    echo '<td style="text-align:center"><span class="label label-info" style="font-size:1em;cursor:default;width:135px;height:20px;">' . $eqB->getStatus('lastCommunication') . ' </span></td>';
    echo '<td style="text-align:center"><span class="label label-info" style="font-size:1em;cursor:default;width:135px;height:20px;">' . $eqB->getConfiguration('createtime') . ' </span></td>';
    echo '<td style="text-align:center"><a class="eqLogicAction" data-action="configureEq"><i class="fas fa-cogs"></i></a> ';
    echo '<a class="eqLogicAction" data-action="removeEq"><i class="fas fa-minus-circle"></i></a></td></tr>';
}
?>
    </tbody>
</table>
<?php
foreach ($eqBrokers as $eqB) { // For each Broker
    echo '<legend><i class="fas fa-table"></i> {{Équipement(s) connectés à}} <b>' . $eqB->getName() . '</b></legend>';
    if (count($eqNonBrokers[$eqB->getId()]) > 0) {
        echo '<table class="table table-condensed tablesorter" id="table_healthMQTT_'.$eqB->getId().'">';
?>
    <thead>
        <tr>
            <th class="col-md-3">{{Module}}</th>
            <th class="col-md-1 center">{{ID}}</th>
            <th class="col-md-4">{{Inscrit au Topic}}</th>
            <th class="col-md-1 center">{{Commandes}}</th>
            <th class="col-md-1 center">{{Dernière comm.}}</th>
            <th class="col-md-1 center">{{Date de création}}</th>
            <th>&nbsp;</th>
        </tr>
    </thead>
    <tbody>
<?php
        foreach ($eqNonBrokers[$eqB->getId()] as $eqL) { // List every equipment on the Broker
            echo '<tr><td><a href="' . $eqL->getLinkToConfiguration() . '" class="eName" data-key="' . $eqL->getHumanName() . '" style="text-decoration: none;">' . $eqL->getHumanName(true) . '</a></td>';
            echo '<td style="text-align:center"><span class="label label-info eId" style="font-size:1em;cursor:default;width:70px">' . $eqL->getId() . '</span></td>';
            echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqL->getTopic() . '</span></td>';
            echo '<td style="text-align:center"><span class="label label-info" style="font-size:1em;cursor:default;width:60px;height:20px;">' . count($eqL->getCmd()) . '</span></td>';
            echo '<td style="text-align:center"><span class="label label-info" style="font-size:1em;cursor:default;width:135px;height:20px;">' . $eqL->getStatus('lastCommunication') . ' </span></td>';
            echo '<td style="text-align:center"><span class="label label-info" style="font-size:1em;cursor:default;width:135px;height:20px;">' . $eqL->getConfiguration('createtime') . ' </span></td>';
            echo '<td style="text-align:center"><a class="eqLogicAction" data-action="configureEq"><i class="fas fa-cogs"></i></a> ';
            echo '<a class="eqLogicAction" data-action="removeEq"><i class="fas fa-minus-circle"></i></a></td></tr>';
        }
?>
    </tbody>
</table>
<?php
    } else {
        echo '<legend></legend>'; // Add some space if no Eq on this Broker
    }
}
?>
<script>
// Callback to remove jMQTT equipment
$('.eqLogicAction[data-action=removeEq]').off('click').on('click', function () {
    // Equivalent to click on $('.eqLogicAction[data-action=remove]') in plugin.template.js
    // Just different eqId/eqName handling

    var eqId = $(this).closest('tr').find('.eId').value();
    if (eqId == undefined) {
        $.fn.showAlert({message: '{{Veuillez sélectionner un équipement à supprimer}}', level: 'danger'});
        return;
    }
    var eqName = $(this).closest('tr').find('.eName').attr('data-key');
    jeedom.eqLogic.getUseBeforeRemove({
        id: eqId,
        error: function(error) { $.fn.showAlert({ message: error.message, level: 'danger' }); },
        success: function(data) {
            var text = `{{Êtes-vous sûr de vouloir supprimer l'équipement <b>${eqName}</b> ?}}`;
            if (Object.keys(data).length > 0) {
                text += ' <br/> {{Il est utilisé par ou utilise :}}<br/>';
                var complement = null;
                for (var i in data) {
                    complement = '';
                    if ('sourceName' in data[i])
                        complement = ' (' + data[i].sourceName + ')';
                    text += '- ' + '<a href="' + data[i].url + '" target="_blank">' + data[i].type + '</a> : <b>' + data[i].name + '</b>' + complement;
                    text += ' <sup><a href="' + data[i].url + '" target="_blank"><i class="fas fa-external-link-alt"></i></a></sup><br/>';
                }
            }
            text = text.substring(0, text.length - 2);
            bootbox.confirm(text, function(result) {
                if (result) {
                    jeedom.eqLogic.remove({
                        type: 'jMQTT',
                        id: eqId,
                        error: function(error) { $.fn.showAlert({ message: error.message, level: 'danger' }); },
                        success: function() {
                            var vars = getUrlVars();
                            var url = 'index.php?';
                            for (var i in vars) {
                                if (i != 'id' && i != 'removeSuccessFull' && i != 'saveSuccessFull')
                                    url += i + '=' + vars[i].replace('#', '') + '&';
                            }
                            url += 'removeSuccessFull=1';
                            jeedomUtils.loadPage(url);
                        }
                    })
                }
            })
        }
    });
});

// Display eqLogic Advanced parameters
$('.eqLogicAction[data-action=configureEq]').off('click').on('click', function() {
    var eqId = $(this).closest('tr').find('.eId').value();
    $('#md_modal3').dialog().load('index.php?v=d&modal=eqLogic.configure&eqLogic_id=' + eqId).dialog('open');
});
</script>
