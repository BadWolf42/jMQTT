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

$node_images = scandir(__DIR__ . '/../../core/img/');
?>

<style>
td.fitwidth											{ white-space: nowrap; }
div.eqLogicThumbnailContainer.containerAsTable i.fa-sign-in-alt.fa-rotate-90	{ margin-bottom: 0px; }
span.hiddenAsTable i.fas.fa-sign-in-alt				{ font-size:0.9em !important;position:absolute;margin-top:67px;margin-left:3px; }
span.hiddenAsTable i.fas.status-circle				{ font-size:1em !important;  position:absolute;margin-top:23px;margin-left:55px; }
span.hiddenAsTable i.fas.eyed						{ font-size:0.9em !important;position:absolute;margin-top:25px;margin-left:4px; }
span.hiddenAsCard i.fas.fa-sign-in-alt				{ margin-right:10px;vertical-align:top;margin-top:-3px; }
span.hiddenAsCard i.fas.status-circle				{ margin-right:6px; }
textarea.form-control.input-sm.modifiedVal			{ color: darkorange!important; font-weight: bold!important; }
div.eqLogicDisplayCard[jmqtt_type="broker"]			{ background: rgba(248, 216, 0, 0.25)!important; }
textarea.eqLogicAttr.form-control.blured			{ filter: blur(4px); }
textarea.eqLogicAttr.form-control.blured:hover		{ filter: none; }
textarea.eqLogicAttr.form-control.blured:focus		{ filter: none; }
textarea.eqLogicAttr.form-control.cert				{ font-family: "CamingoCode", monospace; height: 90px; }
</style>

<?php
function displayActionCard($action_name, $fa_icon, $attr = '', $class = '') {
	echo '<div class="eqLogicAction cursor ' . $class . '" ' . $attr . '>';
	echo '<i class="fas ' . $fa_icon . '"></i><br><span>' . $action_name . '</span></div>';
}

/**
 *
 * @param jMQTT $eqL
 */
function displayEqLogicCard($eqL, $node_images) {
	$opacity = $eqL->getIsEnable() ? '' : ' disableCard';
	echo '<div class="eqLogicDisplayCard cursor' . $opacity . '" data-eqLogic_id="' . $eqL->getId() . '" jmqtt_type="' . $eqL->getType() . '">';
	echo '<span class="hiddenAsTable">';
	if ($eqL->getAutoAddCmd() && $eqL->getType() == jMQTT::TYP_EQPT)
		echo '<i class="fas fa-sign-in-alt fa-rotate-90"></i>';
	echo '<i class="fas eyed ' . (($eqL->getIsVisible()) ? 'fa-eye' : 'fa-eye-slash') . '"></i>';
	if ($eqL->getType() == jMQTT::TYP_BRK) {
		$file = 'node_broker.svg';
		$st = $eqL->getMqttClientState();
		echo '<i class="status-circle fas '.jMQTT::getBrokerIconFromState($st).'"></i>';
	} else {
		$icon = 'node_' . $eqL->getConfiguration('icone');
		$file = (in_array($icon.'.svg', $node_images) ? $icon.'.svg' : (in_array($icon.'.png', $node_images) ? $icon.'.png' : 'node_.png'));
	}
	echo '</span>';
	echo '<img class="lazy" src="plugins/jMQTT/core/img/' . $file . '"/>';
	echo "<br>";
	echo '<span class="name">' . $eqL->getHumanName(true, true) . '</span>';
	echo '<span class="hiddenAsCard displayTableRight hidden">';
	if ($eqL->getAutoAddCmd() && $eqL->getType() == jMQTT::TYP_EQPT) echo '<i class="fas fa-sign-in-alt fa-rotate-90"></i>';
	if ($eqL->getType() == jMQTT::TYP_BRK) echo '<i class="status-circle fas '.jMQTT::getBrokerIconFromState($st).'"></i>';
	echo '<i class="fas ' . (($eqL->getIsVisible()) ? 'fa-eye' : 'fa-eye-slash') . '"></i>';
	echo '</span>';
	echo '</div>';
}

?>

<div id="div_cmdMsg"></div>
<div id="div_newEqptMsg"></div>
<div id="div_inclusionModeMsg"></div>
<?php if(strpos(trim(update::byLogicalId('jeedom')->getLocalVersion()), '3.') === 0) { ?>
<div class="col-xs-12"><span class="label control-label label-danger" style="width:100%;font-size: 13px!important">{{Ceci est la dernière version de jMQTT supportant Jeedom 3. Passez Jeedom en version 4 pour bénéficier des prochaines évolutions de jMQTT}}</span></div>
<?php } ?>
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
					echo '<legend><i class="fas fa-table"></i>{{Equipements orphelins}}</legend>';
					echo '<div class="eqLogicThumbnailContainer">';
					$has_orphans = true;
				}
				foreach ($nonBrokers as $eqL) {
					displayEqLogicCard($eqL, $node_images);
				}
			}
		}
		if ($has_orphans)
			echo '</div>';

		foreach ($eqBrokers as $eqB) {
			echo '<legend><i class="fas fa-table"></i> ';
			if (!array_key_exists($eqB->getId(), $eqNonBrokers))
				echo '{{Aucun équipement connectés à}}';
			elseif (count($eqNonBrokers[$eqB->getId()]) == 1)
				echo '{{1 équipement connectés à}}';
			else
				echo count($eqNonBrokers[$eqB->getId()]).' {{équipements connectés à}}';
			echo ' <b>' . $eqB->getName() . '</b></legend>';
			echo '<div class="eqLogicThumbnailContainer">';
			displayEqLogicCard($eqB, $node_images);
			if (array_key_exists($eqB->getId(), $eqNonBrokers)) {
				foreach ($eqNonBrokers[$eqB->getId()] as $eqL) {
					displayEqLogicCard($eqL, $node_images);
				}
			}
			echo '</div>';
		}
		
		?>
	</div>

	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="row">
			<div class="input-group pull-right" style="display:inline-flex">
				<a class="btn btn-warning btn-sm eqLogicAction typ-std roundedLeft" data-action="applyTemplate"><i class="fas fa-share"></i> {{Appliquer Template}}</a>
				<a class="btn btn-primary btn-sm eqLogicAction typ-std" data-action="createTemplate"><i class="fas fa-cubes"></i> {{Créer Template}}</a>
				<a class="btn btn-success btn-sm eqLogicAction typ-std" data-action="updateTopics"><i class="fas fa-pen"></i> {{Modifier Topics}}</a>
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
				<a class="btn btn-default btn-sm eqLogicAction toDisable" data-action="addMQTTAction"><i class="fas fa-plus-circle"></i> {{Ajouter une commande action}}</a>
				<a class="btn btn-default btn-sm eqLogicAction toDisable" data-action="addMQTTInfo"><i class="fas fa-plus-circle"></i> {{Ajouter une commande info}}</a>
				<div class="btn-group pull-right" data-toggle="buttons">
					<a class="btn btn-primary btn-sm eqLogicAction active" data-action="classicView"><input type="radio" autocomplete="off" checked><i class="fas fa-list-alt"></i> Classic </a>
					<a class="btn btn-default btn-sm eqLogicAction" data-action="jsonView"><input type="radio" autocomplete="off"><i class="fas fa-sitemap"></i> JSON </a>
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

<script>

<?php
// Initialise the automatic inclusion button display according to include_mode configuration parameter
foreach ($eqBrokers as $eqL) {
	echo 'setIncludeModeActivation(' . $eqL->getId() . ',"' . $eqL->getMqttClientState() . '");';
	echo 'configureIncludeModeDisplay(' . $eqL->getId() . ',' . $eqL->getIncludeMode() . ');';
}
?>

</script>
