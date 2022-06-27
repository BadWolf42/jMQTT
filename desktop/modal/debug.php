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
?>

<div style="display: none;" id="md_jmqttDebug"></div>
<!-- <div class="hasfloatingbar col-xs-12 col-lg-12" style=""> -->
<!--
	<div class="floatingbar">
		<div class="input-group">
			<span class="input-group-btn" id="span_right_button"><a class="btn btn-sm roundedLeft bt_refreshPluginInfo"><i class="fas fa-sync"></i> Rafraichir</a><a class="btn btn-primary btn-sm" target="_blank" href="https://domochip.github.io/jMQTT/fr_FR/"><i class="fas fa-book"></i> Documentation</a><a class="btn btn-primary btn-sm" target="_blank" href="https://domochip.github.io/jMQTT/fr_FR/changelog"><i class="fas fa-book"></i> Changelog</a><a class="btn btn-danger btn-sm removePlugin roundedRight" data-market_logicalid="jMQTT"><i class="fas fa-trash"></i> Supprimer</a></span>
		</div>
	</div>
-->
	<script>
function callDebugAjax(_params) {
	$.ajax({
		async: _params.async == undefined ? true : _params.async,
		global: false,
		type: "POST",
		url: "plugins/jMQTT/core/ajax/debug.ajax.php",
		data: _params.data,
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function (data) {
			if (data.state != 'ok') {
				$('#div_alert').showAlert({message: data.result, level: 'danger'});
			}
			else {
				if (typeof _params.success === 'function') {
					_params.success(data.result);
				}
			}
		}
	});
}
	</script>
	<div class="row">
		<div class="col-md-6 col-sm-12">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title"><i class="fas fa-circle-notch"></i> {{Etat Général de Jeedom}}</h3>
				</div>
				<div class="panel-body">
					<form class="form-horizontal">
						<fieldset>
							<div class="form-group">
								<label class="col-sm-3 control-label">Hardware</label>
								<div class="col-sm-3">
									<span><?php echo jeedom::getHardwareName(); ?></span>
								</div>
								<label class="col-sm-3 control-label">Distrib</label>
								<div class="col-sm-3">
									<span><?php echo system::getDistrib(); ?></span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Version</label>
								<div class="col-sm-3">
									<span><?php echo jeedom::version(); ?></span>
								</div>
								<label class="col-sm-3 control-label">Langage</label>
								<div class="col-sm-3">
									<span><?php echo config::byKey('language'); ?></span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Plugins installées</label>
								<div class="col-sm-9">
<?php
$all_plugins = "";
foreach (plugin::listPlugin(false, false, true, true) as $p) // use $_nameOnly=true
	$all_plugins .= ' '.$p;
?>
									<span><?php echo $all_plugins; ?></span>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
			</div>
		</div>
		<div class="col-md-6 col-sm-12">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title"><i class="fas fa-certificate"></i> {{Etat Général de jMQTT}}</h3>
				</div>
				<div class="panel-body">
					<form class="form-horizontal">
						<fieldset>
<?php $jplugin = update::byLogicalId("jMQTT"); ?>
							<div class="form-group">
								<label class="col-sm-3 control-label">Source</label>
								<div class="col-sm-3">
									<span><?php echo $jplugin->getSource(); ?></span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">LogicalId</label>
								<div class="col-sm-3">
									<span><?php echo $jplugin->getLogicalId(); ?></span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Version installée</label>
								<div class="col-sm-9">
									<span><?php echo $jplugin->getLocalVersion(); ?></span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">Version distante</label>
								<div class="col-sm-9">
									<span><?php echo $jplugin->getRemoteVersion(); ?></span>
								</div>
							</div>
						</fieldset>
					</form>
				</div>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-6 col-sm-12">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title"><i class="fas fa-wrench"></i> {{Valeurs de config interne}}</h3>
				</div>
				<div class="panel-body">
					<table class="table table-bordered" id="bt_debugTabConfig" style="table-layout:fixed;width:100%;">
						<thead>
							<tr>
								<th style="width:180px">Clé</th>
								<th>Valeur (encodée en Json)</th>
								<th style="width:85px;text-align:center"><a class="btn btn-success btn-xs pull-right" style="top:0px!important;" id="bt_debugAddConfig"><i class="fas fa-check-circle icon-white"></i> Ajouter</a></th>
							</tr>
						</thead>
						<tbody>
<?php foreach (config::searchKey('', "jMQTT") as $c) { ?>
							<tr>
								<td class="key"><?php echo $c['key']; ?></td>
								<td><pre class="val"><?php echo json_encode($c['value']); ?></pre></td>
								<td style="text-align:center"><a class="btn btn-warning btn-sm bt_debugEditConfig"><i class="fas fa-pen"></i> </a><a class="btn btn-danger btn-sm bt_debugDelConfig"><i class="fas fa-trash"></i> </a></td>
							</tr>
<?php } ?>
						</tbody>
					</table>
					<script>
$('#bt_debugAddConfig').on('click', function () {
	bootbox.confirm({
		title: '{{Ajouter un paramètre de configuration interne}}',
		message: '<label class="control-label">{{Clé :}} </label> '
				+ '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" id="debugKey"><br><br>'
				+ '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
				+ '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br><br>',
		callback: function (result){
			if (result) {
			callDebugAjax({
				data: {
					action: "configSet",
					key : $("#debugKey").val(),
					val: $("#debugVal").val()
				},
				error: function(error) {
					$('#md_jmqttDebug').showAlert({message: error.message, level: 'danger'})
				},
				success: function(data) {
					$('#md_jmqttDebug').showAlert({message: '{{Paramètre de config interne ajouté.}}', level: 'success'});
					var row = $('#bt_debugTabConfig tbody').prepend('<tr />').children('tr:first');//.text($("#debugVal").val());
					row.append('<td class="key">'+$("#debugKey").val()+'</td>');
					row.append('<td><pre class="val">'+$("#debugVal").val()+'</pre></td>');
					row.append('<td style="text-align:center"><a class="btn btn-warning btn-sm bt_debugEditConfig"><i class="fas fa-pen"></i> </a><a class="btn btn-danger btn-sm bt_debugDelConfig"><i class="fas fa-trash"></i> </a></td>');
				}
			});
			}
		}
	});
});

$('#bt_debugTabConfig').on('click', '.bt_debugEditConfig', function () {
	var tr = $(this).closest('tr');
	var debugKey = tr.find('.key').text();
	bootbox.confirm({
		title: '{{Modifier le paramètre de configuration interne}}',
		message: '<label class="control-label">{{Clé :}} </label> '
				+ '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" disabled type="text" value=\''+debugKey+'\'><br><br>'
				+ '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
				+ '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br><br>',
		callback: function (result){
			if (result) {
			callDebugAjax({
				data: {
					action: "configSet",
					key : debugKey,
					val: $("#debugVal").val()
				},
				error: function(error) {
					$('#md_jmqttDebug').showAlert({message: error.message, level: 'danger'})
				},
				success: function(data) {
					$('#md_jmqttDebug').showAlert({message: '{{Paramètre de config interne modifié.}}', level: 'success'});
					tr.find('.val').text($("#debugVal").val());
				}
			});
			}
		}
	});
});

$('#bt_debugTabConfig').on('click', '.bt_debugDelConfig', function () {
	var tr = $(this).closest('tr');
	var debugKey = tr.find('.key').text();
	bootbox.confirm({
		title: '{{Supprimer le paramètre de configuration interne}}',
		message: '<label class="control-label">{{Clé :}} </label> '
				+ '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" disabled type="text" value=\''+debugKey+'\'><br><br>'
				+ '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
				+ '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" disabled readonly=true>'+$(this).closest('tr').find('.val').text()+'</textarea><br><br>',
		callback: function (result){
			if (result) {
			callDebugAjax({
				data: {
					action: "configDel",
					key : debugKey
				},
				error: function(error) {
					$('#md_jmqttDebug').showAlert({message: error.message, level: 'danger'})
				},
				success: function(data) {
					$('#md_jmqttDebug').showAlert({message: '{{Paramètre de config interne supprimé.}}', level: 'success'});
					tr.remove();
				}
			});
			}
		}
	});
});
					</script>
				</div>
			</div>

		</div>
		<div class="col-md-6 col-sm-12">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title"><i class="fas fa-book"></i> {{Valeurs du cache interne}}</h3>
				</div>

				<div class="panel-body">
					<table class="table table-bordered" id="bt_debugTabCache" style="table-layout:fixed;width:100%;">
						<thead>
							<tr>
								<th style="width:240px">Clé</th>
								<th>Valeur (encodée en Json)</th>
								<th style="width:85px;text-align:center"><a class="btn btn-success btn-xs pull-right" style="top:0px!important;" id="bt_debugAddCache"><i class="fas fa-check-circle icon-white"></i> Ajouter</a></th>
							</tr>
						</thead>
						<tbody>
							<tr>
								<td colspan="3" style="font-weight:bolder;color:var(--al-danger-color);">{{Deamon}}</td>
							</tr>
<?php
$cacheKeys = array();
$cacheKeys[] = 'jMQTT::' . jMQTT::CACHE_DAEMON_CONNECTED;
// $cacheKeys[] = 'jMQTT::dummy';
// $cacheKeys[] = ;
foreach ($cacheKeys as $k) {
?>
							<tr>
								<td class="key"><?php echo $k; ?></td>
								<td><pre class="val"><?php echo json_encode(cache::byKey($k)->getValue(null), JSON_UNESCAPED_UNICODE); ?></pre></td>
								<td style="text-align:center"><a class="btn btn-warning btn-sm bt_debugEditCache"><i class="fas fa-pen"></i> </a> <a class="btn btn-danger btn-sm bt_debugDelCache"><i class="fas fa-trash"></i> </a></td>
							</tr>
<?php
}

foreach (jMQTT::getBrokers() as $brk) {
	$cacheBrkKeys = array();
	$cacheBrkKeys[] = 'jMQTT::' . $brk->getId() . '::' . jMQTT::CACHE_MQTTCLIENT_CONNECTED;
	$cacheBrkKeys[] = 'eqLogicCacheAttr'.$brk->getId();
?>
							<tr>
								<td colspan="3" style="font-weight:bolder;color:var(--al-warning-color);">{{Broker}} <?php echo $brk->getHumanName(); ?> (<?php echo $brk->getId(); ?>)</td>
							</tr>
<?php
	foreach ($cacheBrkKeys as $k) {
		$val = cache::byKey($k)->getValue(null);
		if (!is_null($val)) {
?>
							<tr>
								<td class="key"><?php echo $k; ?></td>
								<td><pre class="val"><?php echo json_encode($val, JSON_UNESCAPED_UNICODE); ?></pre></td>
								<td style="text-align:center"><a class="btn btn-warning btn-sm bt_debugEditCache"><i class="fas fa-pen"></i> </a> <a class="btn btn-danger btn-sm bt_debugDelCache"><i class="fas fa-trash"></i> </a></td>
							</tr>
<?php
		}
	}
}

foreach(jMQTT::getNonBrokers() as $eqpts) {
	//jMQTT::byType(jMQTT::class)
	foreach ($eqpts as $eqpt) {
		$cacheEqptKeys = array();
		$cacheEqptKeys[] = 'jMQTT::' . $eqpt->getId() . '::' . jMQTT::CACHE_IGNORE_TOPIC_MISMATCH;
		// $cacheEqptKeys[] = 'jMQTT::' . $eqpt->getId() . '::' . jMQTT::CACHE_MQTTCLIENT_CONNECTED;
		$cacheEqptKeys[] = 'eqLogicCacheAttr'.$eqpt->getId();
		$printEqH = '							<tr><td colspan="3" style="font-weight:bolder;color:var(--al-primary-color);">{{Equipement}} '.$eqpt->getHumanName().' ('.$eqpt->getId().')</td></tr>';
		
		$printEqB = '';
		foreach ($cacheEqptKeys as $k) {
			$val = cache::byKey($k)->getValue(null);
			if (!is_null($val)) {
				$printEqB .= '							<tr>';
				$printEqB .= '<td class="key">'.$k.'</td>';
				$printEqB .= '<td><pre class="val">'.json_encode($val, JSON_UNESCAPED_UNICODE).'</pre></td>';
				$printEqB .= '<td style="text-align:center"><a class="btn btn-warning btn-sm bt_debugEditCache"><i class="fas fa-pen"></i> </a> <a class="btn btn-danger btn-sm bt_debugDelCache"><i class="fas fa-trash"></i> </a></td>';
				$printEqB .= '</tr>';
			}
		}
		if ($printEqB !== '')
			echo $printEqH, $printEqB;
	}
}
/*
// TODO FIXME Listing of cmd in cache WAY TOO SLOW, fetch dynamicaly?

foreach (cmd::searchConfiguration('', jMQTT::class) as $cmd) {
	$cacheCmdKeys = array();
	$cacheCmdKeys[] = 'cmdCacheAttr'.$cmd->getId();
	$printCmdH = '							<tr><td colspan="3" style="font-weight:bolder;color:var(--al-success-color);">{{Commande}} '.$cmd->getHumanName().' ('.$cmd->getId().')</td></tr>';
	
	$printCmdB = '';
	foreach ($cacheCmdKeys as $k) {
		$val = cache::byKey($k)->getValue(null);
		if (!is_null($val)) {
			$printCmdB .= '							<tr>';
			$printCmdB .= '<td class="key">'.$k.'</td>';
			$printCmdB .= '<td><pre class="val">'.json_encode($val, JSON_UNESCAPED_UNICODE).'</pre></td>';
			$printCmdB .= '<td style="text-align:center"><a class="btn btn-warning btn-sm bt_debugEditCache"><i class="fas fa-pen"></i> </a> <a class="btn btn-danger btn-sm bt_debugDelCache"><i class="fas fa-trash"></i> </a></td>';
			$printCmdB .= '</tr>';
		}
	}
	if ($printCmdB !== '')
		echo $printCmdH, $printCmdB;
}*/
?>
						</tbody>
					</table>
					<script>
$('#bt_debugAddCache').on('click', function () {
	bootbox.confirm({
		title: '{{Ajouter un paramètre au cache interne}}',
		message: '<label class="control-label">{{Clé :}} </label> '
				+ '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" id="debugKey"><br><br>'
				+ '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
				+ '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br><br>',
		callback: function (result){
			if (result) {
			callDebugAjax({
				data: {
					action: "cacheSet",
					key : $("#debugKey").val(),
					val: $("#debugVal").val()
				},
				error: function(error) {
					$('#md_jmqttDebug').showAlert({message: error.message, level: 'danger'})
				},
				success: function(data) {
					$('#md_jmqttDebug').showAlert({message: '{{Paramètre de cache interne ajouté.}}', level: 'success'});
					var row = $('#bt_debugTabCache tbody').prepend('<tr />').children('tr:first');//.text($("#debugVal").val());
					row.append('<td class="key">'+$("#debugKey").val()+'</td>');
					row.append('<td><pre class="val">'+$("#debugVal").val()+'</pre></td>');
					row.append('<td style="text-align:center"><a class="btn btn-warning btn-sm bt_debugEditCache"><i class="fas fa-pen"></i> </a><a class="btn btn-danger btn-sm bt_debugDelCache"><i class="fas fa-trash"></i> </a></td>');
				}
			});
			}
		}
	});
});

$('#bt_debugTabCache').on('click', '.bt_debugEditCache', function () {
	var tr = $(this).closest('tr');
	var debugKey = tr.find('.key').text();
	bootbox.confirm({
		title: '{{Modifier le paramètre du cache interne}}',
		message: '<label class="control-label">{{Clé :}} </label> '
				+ '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" disabled type="text" value=\''+debugKey+'\'><br><br>'
				+ '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
				+ '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br><br>',
		callback: function (result){
			if (result) {
			callDebugAjax({
				data: {
					action: "cacheSet",
					key : debugKey,
					val: $("#debugVal").val()
				},
				error: function(error) {
					$('#md_jmqttDebug').showAlert({message: error.message, level: 'danger'})
				},
				success: function(data) {
					$('#md_jmqttDebug').showAlert({message: '{{Paramètre du cache interne modifié.}}', level: 'success'});
					tr.find('.val').text($("#debugVal").val());
				}
			});
			}
		}
	});
});

$('#bt_debugTabCache').on('click', '.bt_debugDelCache', function () {
	var tr = $(this).closest('tr');
	var debugKey = tr.find('.key').text();
	bootbox.confirm({
		title: '{{Supprimer le paramètre du cache interne}}',
		message: '<label class="control-label">{{Clé :}} </label> '
				+ '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" disabled type="text" value=\''+debugKey+'\'><br><br>'
				+ '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
				+ '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" disabled readonly=true>'+$(this).closest('tr').find('.val').text()+'</textarea><br><br>',
		callback: function (result){
			if (result) {
			callDebugAjax({
				data: {
					action: "cacheDel",
					key : debugKey
				},
				error: function(error) {
					$('#md_jmqttDebug').showAlert({message: error.message, level: 'danger'})
				},
				success: function(data) {
					$('#md_jmqttDebug').showAlert({message: '{{Paramètre du cache interne supprimé.}}', level: 'success'});
					tr.remove();
				}
			});
			}
		}
	});
});
					</script>
				</div>

<!--
				<div class="panel-body">
					<form class="form-horizontal">
						<fieldset>
							<div class="form-group">
								<label class="col-sm-12" style="background-color:#f5f5f5!important;">Deamon</label>
							</div>
< ?php
$cacheKeys = array();
$cacheKeys[] = 'jMQTT::' . jMQTT::CACHE_DAEMON_CONNECTED;
// $cacheKeys[] = ;
foreach ($cacheKeys as $k) {
?>
							<div class="form-group">
								<label class="col-sm-4">< ?php echo $k; ?></label>
								<span class="col-sm-7"><pre>< ?php echo json_encode(cache::byKey($k)->getValue(null), JSON_UNESCAPED_UNICODE); ?></pre></span>
								<!--<span class="input-group-btn"><a class="btn btn-warning btn-sm"><i class="fas fa-pen"></i> </a><a class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> </a></span>- ->
							</div>
< ?php
}

foreach (jMQTT::getBrokers() as $brk) {
	$cacheBrkKeys = array();
	$cacheBrkKeys[] = 'jMQTT::' . $brk->getId() . '::' . jMQTT::CACHE_IGNORE_TOPIC_MISMATCH;
	$cacheBrkKeys[] = 'jMQTT::' . $brk->getId() . '::' . jMQTT::CACHE_MQTTCLIENT_CONNECTED;
	$cacheBrkKeys[] = 'eqLogicCacheAttr'.$brk->getId();
?>
							<div class="form-group">
								<label class="col-sm-12" style="background-color:#f5f5f5!important;">Broker < ?php echo $brk->getName(); ?></label>
							</div>
< ?php
	foreach ($cacheBrkKeys as $k) {
?>
							<div class="form-group">
								<label class="col-sm-4">< ?php echo $k; ?></label>
								<span class="col-sm-7"><pre>< ?php echo json_encode(cache::byKey($k)->getValue(null), JSON_UNESCAPED_UNICODE); ?></pre></span>
								<!--<span class="input-group-btn"><a class="btn btn-warning btn-sm"><i class="fas fa-pen"></i> </a><a class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> </a></span>- ->
							</div>
< ?php
	}
}
?>
						</fieldset>
					</form>
				</div>
-->
			</div>
		</div>
	</div>

<!--
TODO IMPLEMENT sent to / receive from daemon
	<div class="row">
		<div class="col-md-6 col-sm-12">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title"><i class="fas fa-upload"></i> {{Simuler un event envoyé au Démon}}</h3>
				</div>
				<div class="panel-body">
					<form class="form-horizontal">
							<fieldset>
							<div><pre>
send_to_mqtt_daemon($params)
	{"cmd": "", "id": "", "hostname": "", "port": "", "clientid": "", "statustopic": "", "username": "", "password": "", "paholog": "", "tls": "", "tlsinsecure": "", "tlscafile": "", "tlsclicertfile": "", "tlsclikeyfile": "", "payload": "", "qos": "", "retain": "", "topic": ""}

new_mqtt_client($id, $hostname, $params = array())
	{"port": "", "clientid": "", "statustopic": "", "username": "", "password": "", "paholog": "", "tls": "", "tlsinsecure": "", "tlscafile": "", "tlsclicertfile": "", "tlsclikeyfile": ""}

remove_mqtt_client($id)
subscribe_mqtt_topic($id, $topic, $qos = 1)
unsubscribe_mqtt_topic($id, $topic)
publish_mqtt_message($id, $topic, $payload, $qos = 1, $retain = false)
send_loglevel()
							</pre></div>
							<legend><i class="fas fa-cog"></i>send_to_mqtt_daemon</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-3">
									<span>XXXXXX</span>
								</div>
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-3">
									<span>XXXXXX</span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-3">
									<span>XXXXXX</span>
								</div>
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-3">
									<span>XXXXXX</span>
								</div>
							</div>
							<legend><i class="fas fa-cog"></i>new_mqtt_client</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-9">
									<span>XXXXXX</span>
								</div>
							</div>
						</fieldset>
					</form>
					<script>
/*
$('#bt_debugToDaemonRaw').on('click', function () {
	bootbox.confirm({
		title: '{{Ajouter un paramètre de configuration interne}}',
		message: '<label class="control-label">{{Clé :}} </label> '
				+ '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" id="debugKey"><br><br>'
				+ '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
				+ '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br><br>',
		callback: function (result){
			if (result) {
			callDebugAjax({
				data: {
					action: "configSet",
					key : $("#debugKey").val(),
					val: $("#debugVal").val()
				},
				error: function(error) {
					$('#md_jmqttDebug').showAlert({message: error.message, level: 'danger'})
				},
				success: function(data) {
					$('#md_jmqttDebug').showAlert({message: '{{Paramètre de config interne ajouté.}}', level: 'success'});
					var row = $('#bt_debugTabConfig tbody').prepend('<tr />').children('tr:first');//.text($("#debugVal").val());
					row.append('<td class="key">'+$("#debugKey").val()+'</td>');
					row.append('<td><pre class="val">'+$("#debugVal").val()+'</pre></td>');
					row.append('<td style="text-align:center"><a class="btn btn-warning btn-sm bt_debugEditConfig"><i class="fas fa-pen"></i> </a><a class="btn btn-danger btn-sm bt_debugDelConfig"><i class="fas fa-trash"></i> </a></td>');
				}
			});
			}
		}
	});
});
*/
					</script>
				</div>
			</div>
		</div>
		<div class="col-md-6 col-sm-12">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title"><i class="fas fa-download"></i> {{Simuler un event reçu du Démon}}</h3>
				</div>
				<div class="panel-body">
					<form class="form-horizontal">
							<fieldset>
							<div><pre>
METHOD POST
"uid" in URL
{"cmd":"messageIn", "id":string, "topic":string, "payload":string, "qos":string, "retain":string}
{"cmd":"brokerUp", "id":string}
{"cmd":"brokerDown"}
{"cmd":"daemonUp"}
{"cmd":"daemonDown"}
{"cmd":"hb"}
							</pre></div>
							<legend><i class="fas fa-cog"></i>messageIn</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-3">
									<span>XXXXXX</span>
								</div>
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-3">
									<span>XXXXXX</span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-3">
									<span>XXXXXX</span>
								</div>
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-3">
									<span>XXXXXX</span>
								</div>
							</div>
							<legend><i class="fas fa-cog"></i>brokerUp</legend>
							<div class="form-group">
								<label class="col-sm-3 control-label">XXXXXX</label>
								<div class="col-sm-9">
									<span>XXXXXX</span>
								</div>
							</div>
						</fieldset>
					</form>
					<script>
// TODO Placeholder
					</script>
				</div>
			</div>
		</div>
	</div>
-->
