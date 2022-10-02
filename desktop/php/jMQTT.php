<?php
if (! isConnect('admin')) {
	throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'jMQTT');
/** @var jMQTT[][] $eqNonBrokers */
$eqNonBrokers = jMQTT::getNonBrokers();
/** @var jMQTT[] $eqBrokers */
$eqBrokers = jMQTT::getBrokers();

$eqBrokersName = array();
foreach ($eqBrokers as $id => $eqL) {
	$eqBrokersName[$id] = $eqL->getName();
}
sendVarToJS('eqBrokers', $eqBrokersName);

// $node_images = scandir(__DIR__ . '/../../core/img/');
$icons = array(
	['id' => '', 'name' => __('Aucun', __FILE__), 'file' => 'node_.svg'],
	['id' => 'barometre', 'name' => __('Baromètre', __FILE__), 'file' => 'node_barometre.svg'],
	['id' => 'bell', 'name' => __('Sonnerie', __FILE__), 'file' => 'node_bell.svg'],
	['id' => 'boiteauxlettres', 'name' => __('Boite aux Lettres', __FILE__), 'file' => 'node_boiteauxlettres.svg'],
	['id' => 'bt', 'name' => __('Bluetooth', __FILE__), 'file' => 'node_bt.svg'],
	['id' => 'chauffage', 'name' => __('Chauffage', __FILE__), 'file' => 'node_chauffage.svg'],
	['id' => 'compteur', 'name' => __('Compteur', __FILE__), 'file' => 'node_compteur.svg'],
	['id' => 'contact', 'name' => __('Contact', __FILE__), 'file' => 'node_contact.svg'],
	['id' => 'custom', 'name' => __('Custom', __FILE__), 'file' => 'node_custom.svg'],
	['id' => 'dimmer', 'name' => __('Dimmer', __FILE__), 'file' => 'node_dimmer.svg'],
	['id' => 'door', 'name' => __('Porte', __FILE__), 'file' => 'node_door.svg'],
	['id' => 'energie', 'name' => __('Energie', __FILE__), 'file' => 'node_energie.svg'],
	['id' => 'fan', 'name' => __('Ventilation', __FILE__), 'file' => 'node_fan.svg'],
	['id' => 'feuille', 'name' => __('Culture', __FILE__), 'file' => 'node_feuille.svg'],
	['id' => 'fire', 'name' => __('Incendie', __FILE__), 'file' => 'node_fire.svg'],
	['id' => 'garage', 'name' => __('Garage', __FILE__), 'file' => 'node_garage.svg'],
	['id' => 'gate', 'name' => __('Portail', __FILE__), 'file' => 'node_gate.svg'],
	['id' => 'home-flood', 'name' => __('Inondation', __FILE__), 'file' => 'node_home-flood.svg'],
	['id' => 'humidity', 'name' => __('Humidité', __FILE__), 'file' => 'node_humidity.png'],
	['id' => 'humiditytemp', 'name' => __('Humidité et Température', __FILE__), 'file' => 'node_humiditytemp.png'],
	['id' => 'hydro', 'name' => __('Hydrométrie', __FILE__), 'file' => 'node_hydro.png'],
	['id' => 'ir2', 'name' => __('Infra Rouge', __FILE__), 'file' => 'node_ir2.png'],
	['id' => 'jauge', 'name' => __('Jauge', __FILE__), 'file' => 'node_jauge.svg'],
	['id' => 'light', 'name' => __('Luminosité', __FILE__), 'file' => 'node_light.png'],
	['id' => 'lightbulb', 'name' => __('Lumière', __FILE__), 'file' => 'node_lightbulb.svg'],
	['id' => 'meteo', 'name' => __('Météo', __FILE__), 'file' => 'node_meteo.png'],
	['id' => 'molecule-co', 'name' => __('CO', __FILE__), 'file' => 'node_molecule-co.svg'],
	['id' => 'motion', 'name' => __('Mouvement', __FILE__), 'file' => 'node_motion.png'],
	['id' => 'motion-sensor', 'name' => __('Présence', __FILE__), 'file' => 'node_motion-sensor.svg'],
	['id' => 'multisensor', 'name' => __('Multisensor', __FILE__), 'file' => 'node_multisensor.png'],
	['id' => 'nab', 'name' => __('Nabaztag', __FILE__), 'file' => 'node_nab.png'],
	['id' => 'power-plug', 'name' => __('Prise de courant', __FILE__), 'file' => 'node_power-plug.svg'],
	['id' => 'prise', 'name' => __('Prise', __FILE__), 'file' => 'node_prise.png'],
	['id' => 'radiator', 'name' => __('Radiateur', __FILE__), 'file' => 'node_radiator.svg'],
	['id' => 'relay', 'name' => __('Relais', __FILE__), 'file' => 'node_relay.png'],
	['id' => 'remote', 'name' => __('Télécommande', __FILE__), 'file' => 'node_remote.svg'],
	['id' => 'rf433', 'name' => __('RF433', __FILE__), 'file' => 'node_rf433.svg'],
	['id' => 'rfid', 'name' => __('RFID', __FILE__), 'file' => 'node_rfid.png'],
	['id' => 'sms', 'name' => __('SMS', __FILE__), 'file' => 'node_sms.png'],
	['id' => 'teleinfo', 'name' => __('Téléinfo', __FILE__), 'file' => 'node_teleinfo.png'],
	['id' => 'temp', 'name' => __('Température', __FILE__), 'file' => 'node_temp.png'],
	['id' => 'thermostat', 'name' => __('Thermostat', __FILE__), 'file' => 'node_thermostat.png'],
	['id' => 'tv', 'name' => __('Télévison', __FILE__), 'file' => 'node_tv.svg'],
	['id' => 'volet', 'name' => __('Volet', __FILE__), 'file' => 'node_volet.svg'],
	['id' => 'water-boiler', 'name' => __('Chaudière', __FILE__), 'file' => 'node_water-boiler.svg'],
	['id' => 'wifi', 'name' => __('Wifi', __FILE__), 'file' => 'node_wifi.svg'],
	['id' => 'window-closed-variant', 'name' => __('Fenêtre', __FILE__), 'file' => 'node_window-closed-variant.svg'],
	['id' => 'zigbee', 'name' => __('Zigbee', __FILE__), 'file' => 'node_zigbee.svg'],
	['id' => 'zwave', 'name' => __('ZWave', __FILE__), 'file' => 'node_zwave.svg']
);
usort($icons, function ($a, $b) { return strcmp($a["name"], $b["name"]); });
sendVarToJS('jmqttIcons', $icons);

?>

<style>
td.fitwidth											{ white-space: nowrap; }
div.eqLogicThumbnailContainer.containerAsTable i.fa-sign-in-alt.fa-rotate-90	{ margin-bottom: 0px; }
span.hiddenAsTable i.fas.fa-sign-in-alt				{ font-size:0.9em !important;position:absolute;margin-top:67px;margin-left:3px; }
span.hiddenAsTable i.far.fa-square					{ font-size:0.9em !important;position:absolute;margin-top:67px;margin-left:5px; }
span.hiddenAsTable i.fas.status-circle				{ font-size:1em !important;  position:absolute;margin-top:23px;margin-left:55px; }
span.hiddenAsTable i.fas.eyed						{ font-size:0.9em !important;position:absolute;margin-top:25px;margin-left:4px; }
span.hiddenAsCard i.fas.fa-sign-in-alt				{ margin-right:10px;vertical-align:top;margin-top:-3px;margin-left:-5px!important; }
span.hiddenAsCard i.fas.status-circle				{ margin-right:6px; }
textarea.form-control.input-sm.modifiedVal			{ color: darkorange!important; font-weight: bold!important; }
div.eqLogicDisplayCard[jmqtt_type="broker"]			{ background: rgba(248, 216, 0, 0.25)!important; }
textarea.eqLogicAttr.form-control.blured			{ filter: blur(4px); }
textarea.eqLogicAttr.form-control.blured:hover		{ filter: none; }
textarea.eqLogicAttr.form-control.blured:focus		{ filter: none; }
i.fas.fa-minus-circle.cmdAction						{ margin-top: 5px; }
textarea.eqLogicAttr.form-control.cert				{ font-family: "CamingoCode", monospace; height: 90px; }
.w30												{ width: 30px; }
.w18												{ width: 18px; text-align: center; font-size: 0.9em; }
</style>

<?php
/**
 *
 * @param jMQTT $eqL
 * @param array $icons
 */
function displayEqLogicCard($eqL, $icons) {
	$btnData = array();
	$btnData['enabled'] = boolval($eqL->getIsEnable());
	$btnData['visible'] = boolval($eqL->getIsVisible());
	if ($eqL->getType() != jMQTT::TYP_EQPT) {
		$info = $eqL->getMqttClientInfo();
		$btnData['broker'] = true;
		$btnData['state'] = $info['state'];
		$btnData['icon'] = $info['icon'];
		$btnData['include'] = boolval($eqL->getIncludeMode());
	} else {
		$btnData['eqpt'] = false;
		$btnData['include'] = boolval($eqL->getAutoAddCmd());
		$btnData['bat'] = ($eqL->getConfiguration('battery_cmd') == '' ? '0' : ($eqL->getStatus('batterydanger') ? '1' : ($eqL->getStatus('batterywarning') ? '2' : '3')));
		$btnData['avail'] = ($eqL->getConfiguration('availability_cmd') == '' ? '0' : ($eqL->getStatus('warning') ? '1' : '2'));
	}
	echo '<div class="eqLogicDisplayCard cursor' . ($eqL->getIsEnable() ? '' : ' disableCard') . '" data-eqLogic_id="' . $eqL->getId() . '" jmqtt_type="' . $eqL->getType() . '"  btn-data="' .htmlentities(json_encode($btnData), ENT_QUOTES, 'UTF-8'). '">';
	echo '<span class="hiddenAsTable"></span>';
	if ($eqL->getType() == jMQTT::TYP_BRK) {
		$file = 'node_broker.svg';
	} else {
		$icon = $eqL->getConfiguration('icone');
		$key = array_search($icon, array_column($icons, 'id'));
		$file = ($key ? $icons[$key]['file'] : 'node_.svg');
	}
	echo '<img class="lazy" src="plugins/jMQTT/core/img/' . $file . '"/>';
	echo '<br>';
	echo '<span class="name">' . $eqL->getHumanName(true, true) . '</span>';
	echo '<span class="hiddenAsCard input-group displayTableRight hidden"></span>';
	echo "</div>\n";
}

?>

<div id="div_cmdMsg"></div>
<div id="div_newEqptMsg"></div>
<div id="div_inclusionModeMsg"></div>
<?php
// Warning banner for Jeedom v3 users.
if(strpos(trim(config::byKey('version')), '3.') === 0) {
	echo '<span class="label control-label label-danger" style="width:100%;font-size:13px!important;padding:0px;">{{Ceci est la dernière version de jMQTT supportant Jeedom 3. Passez Jeedom en version 4 pour bénéficier des prochaines évolutions de jMQTT}}</span>';
}

function displayActionCard($action_name, $fa_icon, $attr = '', $class = '') {
	echo '<div class="eqLogicAction cursor ' . $class . '" ' . $attr . '>';
	echo '<i class="fas ' . $fa_icon . '"></i><br><span>' . $action_name . '</span></div>';
}
?>
<div class="row row-overflow">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
		<?php
		displayActionCard('{{Configuration}}', 'fa-wrench', 'data-action="gotoPluginConf"', 'logoSecondary');
		displayActionCard('{{Ajouter un broker}}', 'fa-server', 'data-action="addJmqttBrk"', 'logoSecondary');
		displayActionCard('{{Santé}}', 'fa-medkit', 'data-action="healthMQTT"', 'logoSecondary');
		if (isset($_GET['debug']))
		// if ((log::getLogLevel('jMQTT') <= 100) || (config::byKey('debugMode', 'jMQTT', "0") === "1")) // || (isset($_GET['debug']))
			displayActionCard('{{Debug}}', 'fa-bug', 'data-action="debugJMQTT"', 'logoSecondary');
		displayActionCard('{{Templates}}', 'fa-cubes', 'data-action="templatesMQTT"', 'logoSecondary');
		// displayActionCard('{{Découverte}}', 'fa-flag', 'data-action="discoveryJMQTT"', 'logoSecondary');
		// displayActionCard('{{Temps réel}}', 'fa-stream', 'data-action="realTimeJMQTT"', 'logoSecondary');
		displayActionCard('{{Ajouter}}', 'fa-plus-circle', 'data-action="addJmqttEq"', 'logoSecondary');
		?>
		</div>
		<div class="input-group" style="margin:5px;">
			<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">
			<div class="input-group-btn">
				<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>
				<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>
			</div>
		</div>
		<?php
		// Check there are orphans first
		$has_orphans = false;
		foreach ($eqNonBrokers as $id => $nonBrokers) {
			if (! array_key_exists($id, $eqBrokers)) {
				if (!$has_orphans) {
					echo '<legend class="danger"><i class="fas fa-table"></i> {{Mes Equipements orphelins}} <sup><i class="fas fa-exclamation-triangle tooltips" title="{{Ces équipements ne sont associés à aucun broker et ne peuvent donc pas communiquer.<br>Il ne devrait pas y avoir d\'équipement orphelin : supprimez-les ou rattachez-les à un broker.}}"></i></sup></legend>';
					echo '<div class="eqLogicThumbnailContainer">';
					$has_orphans = true;
				}
				foreach ($nonBrokers as $eqL) {
					displayEqLogicCard($eqL, $icons);
				}
			}
		}
		if ($has_orphans)
			echo '</div>';

		foreach ($eqBrokers as $eqB) {
			echo '<legend><i class="fas fa-table"></i> {{Mes Equipements sur le broker }} <b>' . $eqB->getName() . '</b> (' . @count($eqNonBrokers[$eqB->getId()]) . ')</legend>';
			echo '<div class="eqLogicThumbnailContainer">';
			displayEqLogicCard($eqB, $icons);
			if (array_key_exists($eqB->getId(), $eqNonBrokers)) {
				foreach ($eqNonBrokers[$eqB->getId()] as $eqL) {
					displayEqLogicCard($eqL, $icons);
				}
			}
			echo '</div>';
		}
		
		?>
	</div>

	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="row">
			<div class="input-group pull-right" style="display:inline-flex">
				<a class="btn btn-warning btn-sm eqLogicAction typ-std roundedLeft toDisable" data-action="applyTemplate"><i class="fas fa-share"></i> {{Appliquer Template}}</a>
				<a class="btn btn-primary btn-sm eqLogicAction typ-std toDisable" data-action="createTemplate"><i class="fas fa-cubes"></i> {{Créer Template}}</a>
				<a class="btn btn-success btn-sm eqLogicAction typ-std toDisable" data-action="updateTopics"><i class="fas fa-pen"></i> {{Modifier Topics}}</a>
				<a class="btn btn-default btn-sm eqLogicAction typ-brk roundedLeft toDisable" data-action="startIncludeMode"><i class="fas fa-sign-in-alt fa-rotate-90"></i> {{Mode inclusion}}</a>
				<a class="btn btn-default btn-sm eqLogicAction typ-brk btn-danger roundedLeft toDisable" data-action="stopIncludeMode"><i class="fas fa-square"></i> {{Arrêter l'inclusion}}</a>
				<a class="btn btn-default btn-sm eqLogicAction" data-action="configure"><i class="fas fa-cogs"></i> {{Configuration avancée}}</a>
				<a class="btn btn-default btn-sm eqLogicAction typ-std toDisable" data-action="copy"><i class="fas fa-copy"></i> {{Dupliquer}}</a>
				<a class="btn btn-success btn-sm eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
				<a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="removeJmqtt"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>&nbsp;
			</div>
			<div class="input-group pull-left" style="display:inline-flex">
				<ul class="nav nav-tabs" role="tablist">
					<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
					<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="eqlogictab" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
					<li role="presentation" class="typ-brk" style="display: none;"><a href="#brokertab" aria-controls="brokertab" role="tab" data-toggle="tab"><i class="fas fa-rss"></i> {{Broker}}</a></li>
					<li role="presentation"><a href="#commandtab" aria-controls="commandtab" role="tab" data-toggle="tab"><i class="fas fa-list-alt"></i> {{Commandes}}</a></li>
					<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="refreshPage"><i class="fas fa-sync"></i></a></li>
				</ul>
			</div>
		</div>
		<div id="menu-bar" style="display: none;">
			<div class="form-actions">
				<a class="btn btn-info btn-xs eqLogicAction toDisable" data-action="addMQTTAction"><i class="fas fa-plus-circle"></i> {{Ajouter une commande action}}</a>
				<a class="btn btn-warning btn-xs eqLogicAction toDisable" data-action="addMQTTInfo"><i class="fas fa-plus-circle"></i> {{Ajouter une commande info}}</a>
				<div class="btn-group pull-right" data-toggle="buttons">
					<a class="btn btn-primary btn-xs roundedLeft eqLogicAction active" data-action="classicView"><input type="radio" autocomplete="off" checked><i class="fas fa-list-alt"></i> Classic </a>
					<a class="btn btn-default btn-xs roundedRight eqLogicAction" data-action="jsonView"><input type="radio" autocomplete="off"><i class="fas fa-sitemap"></i> JSON </a>
				</div>
			</div>
			<hr style="margin-top: 5px; margin-bottom: 5px;">
		</div>
		<div class="tab-content" style="height:calc(100vh - 140px)!important;overflow:auto;overflow-x:hidden;">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<?php include_file('desktop', 'jMQTT_eqpt', 'php', 'jMQTT'); ?>
			</div>
			<div role="tabpanel" class="tab-pane toDisable" id="brokertab">
				<?php include_file('desktop', 'jMQTT_broker', 'php', 'jMQTT'); ?>
			</div>
			<!-- TODO Add here realtime/stream tab, "Classic Discovery" tab and HA MQTT discovery tab -->
			<!-- TODO Remove all commands on Brokers -->
			<div role="tabpanel" class="tab-pane toDisable" id="commandtab">
				<table id="table_cmd" class="table tree table-bordered table-condensed table-striped">
					<thead>
						<tr>
							<th style="width:1px;">#</th>
							<th style="min-width:150px;width:300px;">{{Nom}}</th>
							<th style="width:130px;">{{Type}}</th>
							<th style="min-width:180px;">{{Topic}}</th>
							<th style="min-width:180px;">{{Valeur}}</th>
							<th style="width:1px;">{{Unité}}</th>
							<th style="min-width:100px;width:120px;">{{Options}}</th>
							<th style="min-width:135px;width:135px;"></th>
<!-- TODO Change when adding Advanced parameters
							<th style="min-width:90px;width:100px;">{{Options}}</th>
							<th style="min-width:160px;width:160px;"></th>
-->
						</tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<?php include_file('desktop', 'jMQTT', 'js', 'jMQTT'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
