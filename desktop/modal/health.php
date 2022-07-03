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
			<th class="col-md-1">{{ID}}</th>
			<th class="col-md-1 center">{{Etat}}</th>
			<th class="col-md-4">{{Statut}}</th>
			<th class="col-md-2">{{Dernière communication}}</th>
			<th class="col-md-1">{{Date de création}}</th>
		</tr>
	</thead>
	<tbody>
<?php

foreach ($eqBrokers as $eqB) {
	echo '<tr><td><a href="' . $eqB->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqB->getHumanName(true) . '</a></td>';
	echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqB->getId() . '</span></td>';
	echo '<td class="center">';
	$info = $eqB->getMqttClientInfo();
	if ($info['state'] == jMQTT::MQTTCLIENT_NOK) { //!$eqB->getIsEnable()
		echo '<i class="fas '.$info['icon'].' w18 tooltips" title="{{Connexion au Broker désactivée}}"></i> ';
		echo '<i class="fas fa-eye-slash w18 tooltips" title="{{Connexion désactivée}}"></i> ';
		echo '<i class="far fa-square w18 fa-rotate-90 tooltips" title="{{Connexion désactivé}}"></i> ';
	} else {
		echo '<i class="fas '.$info['icon'].' w18 tooltips" title="'.(($info['state'] == jMQTT::MQTTCLIENT_OK) ? '{{Connection au Broker active}}' : '{{Connexion au Broker en échec}}').'"></i> ';
		if ($eqB->getIsVisible())
			echo '<i class="fas fa-eye w18 tooltips" title="{{Broker visible}}"></i> ';
		else
			echo '<i class="fas fa-eye-slash warning w18 tooltips" title="{{Broker masqué}}"></i> ';
		if ($eqB->getIncludeMode())
			echo '<i class="fas fa-sign-in-alt warning w18 fa-rotate-90 tooltips" title="{{Inclusion automatique activée}}"></i> ';
		else
			echo '<i class="far fa-square w18 fa-rotate-90 tooltips" title="{{Inclusion automatique désactivée}}"></i> ';
	}
	echo '</td>';
	echo '<td>' . $info['message'] . '</td>';
	echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqB->getStatus('lastCommunication') . '</span></td>';
	echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqB->getConfiguration('createtime') . '</span></td></tr>';
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
			<th class="col-md-1">{{ID}}</th>
			<th class="col-md-1 center">{{Etat}}</th>
			<th class="col-md-4">{{Inscrit au Topic}}</th>
			<th class="col-md-2">{{Dernière communication}}</th>
			<th class="col-md-1">{{Date de création}}</th>
		</tr>
	</thead>
	<tbody>
<?php
		foreach ($eqNonBrokers[$eqB->getId()] as $eqL) {
			echo '<tr><td><a href="' . $eqL->getLinkToConfiguration() . '" style="text-decoration: none;">' . $eqL->getHumanName(true) . '</a></td>';
			echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqL->getId() . '</span></td>';
			echo '<td class="center">';
			if (!$eqL->getIsEnable()) {
				echo '<i class="fas fa-times danger w18 tooltips" title="{{Equipement désactivé}}"></i> ';
				echo '<i class="fas fa-eye-slash w18 tooltips" title="{{Equipement désactivé}}"></i> ';
				echo '<i class="far fa-square fa-rotate-90 w18 tooltips" title="{{Equipement désactivé}}"></i> ';
				echo '<i class="fas fa-plug w18 tooltips" title="{{Equipement désactivé}}"></i> ';
				echo '<i class="fas fa-bell-slash w18 tooltips" title="{{Equipement désactivé}}"></i>';
				
			} else {
					echo '<i class="fas fa-check success w18 tooltips" title="{{Equipement activé}}"></i> ';
				if ($eqL->getIsVisible())
					echo '<i class="fas fa-eye w18 tooltips" title="{{Equipement visible}}"></i> ';
				else
					echo '<i class="fas fa-eye-slash warning w18 tooltips" title="{{Equipement masqué}}"></i> ';
				if ($eqL->getAutoAddCmd())
					echo '<i class="fas fa-sign-in-alt warning fa-rotate-90 w18 tooltips" title="{{Inclusion automatique activée}}"></i> ';
				else
					echo '<i class="far fa-square fa-rotate-90 w18 tooltips" title="{{Inclusion automatique désactivée}}"></i> ';
				if ($eqL->getConfiguration('battery_cmd') == '')
					echo '<i class="fas fa-plug w18 tooltips" title="{{Pas d\'état de la batterie}}"></i> ';
				elseif ($eqL->getStatus('batterydanger'))
					echo '<i class="fas fa-battery-empty w18 danger tooltips" title="{{Batterie en fin de vie}}"></i> ';
				elseif ($eqL->getStatus('batterywarning'))
					echo '<i class="fas fa-battery-quarter w18 warning tooltips" title="{{Batterie en alarme}}"></i> ';
				else
					echo '<i class="fas fa-battery-full w18 success tooltips" title="{{Batterie OK}}"></i> ';
				if ($eqL->getConfiguration('availability_cmd') == '')
					echo '<i class="fas fa-bell-slash w18 tooltips" title="{{Pas d\'état de disponibilité}}"></i>';
				elseif ($eqL->getStatus('warning'))
					echo '<i class="fas fa-bell warning w18 tooltips" title="{{Equipement indisponible}}"></i>';
				else
					echo '<i class="fas fa-bell-slash success w18 tooltips" title="{{Equipement disponible}}"></i>';
			}
			echo '</td>';
			echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqL->getTopic() . '</span></td>';
			echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqL->getStatus('lastCommunication') . '</span></td>';
			echo '<td><span class="label label-info" style="font-size:1em;cursor:default;">' . $eqL->getConfiguration('createtime') . '</span></td></tr>';
		}
?>
	</tbody>
</table>
<?php
	} else {
		echo '<legend></legend>'; // Add some space if no Eq on this Broker
	}
}
