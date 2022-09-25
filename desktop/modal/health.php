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

?>
<style>
.w18 {
	width: 18px;
	text-align: center;
	font-size: 0.9em;
}
</style>
<legend><i class="fas fa-table"></i> {{Brokers}}</legend>
<table class="table table-condensed tablesorter" id="table_healthMQTT_brk">
	<thead>
		<tr>
			<th class="col-md-3">{{Broker}}</th>
			<th class="col-md-1 center">{{ID}}</th>
			<th class="col-md-5">{{Statut}}</th>
			<th class="col-md-1 center">{{Dernière comm.}}</th>
			<th class="col-md-1 center">{{Date de création}}</th>
			<th>&nbsp;</th>
		</tr>
	</thead>
	<tbody>
<?php

foreach ($eqBrokers as $eqB) {
	echo '<tr><td><a href="' . $eqB->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqB->getHumanName(true) . '</a></td>';
	echo '<td class="center"><span class="label label-info" style="font-size:1em;cursor:default;width:70px">' . $eqB->getId() . '</span></td>';
	echo '<td>' . $eqB->getMqttClientInfo()['message'] . '</td>';
	echo '<td class="center"><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqB->getStatus('lastCommunication') . '</span></td>';
	echo '<td class="center"><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqB->getConfiguration('createtime') . '</span></td>';
	echo '<td class="center">&nbsp;</td></tr>';
}

?>
	</tbody>
</table>
<?php

foreach ($eqBrokers as $eqB) {
	echo '<legend><i class="fas fa-table"></i> ';
	if (!array_key_exists($eqB->getId(), $eqNonBrokers))
		echo '{{Aucun équipement connectés à}}';
	elseif (count($eqNonBrokers[$eqB->getId()]) == 1)
		echo '{{1 équipement connectés à}}';
	else
		echo count($eqNonBrokers[$eqB->getId()]).' {{équipements connectés à}}';
	echo ' <b>' . $eqB->getName() . '</b></legend>';
	if (array_key_exists($eqB->getId(), $eqNonBrokers)) {
		echo '<table class="table table-condensed tablesorter" id="table_healthMQTT_'.$eqB->getId().'">';
?>
	<thead>
		<tr>
			<th class="col-md-3">{{Module}}</th>
			<th class="col-md-1 center">{{ID}}</th>
			<th class="col-md-4">{{Inscrit au Topic}}</th>
			<th class="col-md-1 center">{{Nb de cmd}}</th>
			<th class="col-md-1 center">{{Dernière comm.}}</th>
			<th class="col-md-1 center">{{Date de création}}</th>
			<th>&nbsp;</th>
		</tr>
	</thead>
	<tbody>
<?php
		foreach ($eqNonBrokers[$eqB->getId()] as $eqL) {
			echo '<tr><td><a href="' . $eqL->getLinkToConfiguration() . '" class="hName" data-key="' . $eqL->getHumanName() . '" style="text-decoration: none;">' . $eqL->getHumanName(true) . '</a></td>';
			echo '<td class="center"><span class="label label-info hId" style="font-size:1em;cursor:default;width:70px">' . $eqL->getId() . '</span></td>';
			echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqL->getTopic() . '</span></td>';
			echo '<td class="center"><span class="label label-info" style="font-size:1em;cursor:default;width:60px">' . count($eqL->getCmd()) . '</span></td>';
			echo '<td class="center"><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqL->getStatus('lastCommunication') . '</span></td>';
			echo '<td class="center"><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqL->getConfiguration('createtime') . '</span></td>';
			echo '<td class="center"><i class="fas fa-cogs eqLogicAction" data-action="configure"></i> <i class="fas fa-minus-circle eqLogicAction" data-action="removeEq"></i></td></tr>';
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
// Remove jMQTT equipment callback
$('.eqLogicAction[data-action=removeEq]').off('click').on('click', function () {
	var eqId = $(this).closest('tr').find('.hId').value();
	// console.log('removeEq', $(this).closest('tr').find('.hId'), $(this).closest('tr').find('.hName').attr('data-key'), this);
	if (eqId == undefined) {
		$('#div_alert').showAlert({message: '{{Veuillez sélectionner un équipement à supprimer}}', level: 'danger'});
		return;
	}
	var eqName = $(this).closest('tr').find('.hName').attr('data-key');
	bootbox.confirm('{{Etes-vous sûr de vouloir supprimer}}' + ' ' + "{{l'équipement}}" + ' <b>' + eqName + '</b> ?', function (result) {
		if (result) {
			jeedom.eqLogic.remove({
				type: 'jMQTT',
				id: eqId,
				error: function (error) {
					$('#div_alert').showAlert({message: error.message, level: 'danger'});
				},
				success: function () {
					var url = initPluginUrl();
					modifyWithoutSave = false;
					url += '&removeSuccessFull=1';
					loadPage(url);
				}
			});
		}
	});
});

// Display eqLogic Advanced parameters
$('.eqLogicAction[data-action=configure]').off('click').on('click', function() {
	var eqId = $(this).closest('tr').find('.hId').value();
	$('#md_modal3').dialog().load('index.php?v=d&modal=eqLogic.configure&eqLogic_id=' + eqId).dialog('open');
});
</script>
