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

// New namespace
function jmqtt() {}

jmqtt.icons = [
	{id: '', name: "{{Aucun}}", file: 'node_.svg'},
	{id: 'barometre', name: "{{Baromètre}}", file: 'node_barometre.svg'},
	{id: 'bell', name: "{{Sonnerie}}", file: 'node_bell.svg'},
	{id: 'boiteauxlettres', name: "{{Boite aux Lettres}}", file: 'node_boiteauxlettres.svg'},
	{id: 'bt', name: "{{Bluetooth}}", file: 'node_bt.svg'},
	{id: 'chauffage', name: "{{Chauffage}}", file: 'node_chauffage.svg'},
	{id: 'compteur', name: "{{Compteur}}", file: 'node_compteur.svg'},
	{id: 'contact', name: "{{Contact}}", file: 'node_contact.svg'},
	{id: 'custom', name: "{{Custom}}", file: 'node_custom.svg'},
	{id: 'dimmer', name: "{{Dimmer}}", file: 'node_dimmer.svg'},
	{id: 'door', name: "{{Porte}}", file: 'node_door.svg'},
	{id: 'energie', name: "{{Energie}}", file: 'node_energie.svg'},
	{id: 'fan', name: "{{Ventilation}}", file: 'node_fan.svg'},
	{id: 'feuille', name: "{{Culture}}", file: 'node_feuille.svg'},
	{id: 'fire', name: "{{Incendie}}", file: 'node_fire.svg'},
	{id: 'garage', name: "{{Garage}}", file: 'node_garage.svg'},
	{id: 'gate', name: "{{Portail}}", file: 'node_gate.svg'},
	{id: 'home-flood', name: "{{Inondation}}", file: 'node_home-flood.svg'},
	{id: 'humidity', name: "{{Humidité}}", file: 'node_humidity.png'},
	{id: 'humiditytemp', name: "{{Humidité et Température}}", file: 'node_humiditytemp.png'},
	{id: 'hydro', name: "{{Hydrométrie}}", file: 'node_hydro.png'},
	{id: 'ir2', name: "{{Infra Rouge}}", file: 'node_ir2.png'},
	{id: 'jauge', name: "{{Jauge}}", file: 'node_jauge.svg'},
	{id: 'light', name: "{{Luminosité}}", file: 'node_light.png'},
	{id: 'lightbulb', name: "{{Lumière}}", file: 'node_lightbulb.svg'},
	{id: 'meteo', name: "{{Météo}}", file: 'node_meteo.png'},
	{id: 'molecule-co', name: "{{CO}}", file: 'node_molecule-co.svg'},
	{id: 'motion', name: "{{Mouvement}}", file: 'node_motion.png'},
	{id: 'motion-sensor', name: "{{Présence}}", file: 'node_motion-sensor.svg'},
	{id: 'multisensor', name: "{{Multisensor}}", file: 'node_multisensor.png'},
	{id: 'nab', name: "{{Nabaztag}}", file: 'node_nab.png'},
	{id: 'power-plug', name: "{{Prise de courant}}", file: 'node_power-plug.svg'},
	{id: 'prise', name: "{{Prise}}", file: 'node_prise.png'},
	{id: 'radiator', name: "{{Radiateur}}", file: 'node_radiator.svg'},
	{id: 'relay', name: "{{Relais}}", file: 'node_relay.png'},
	{id: 'remote', name: "{{Télécommande}}", file: 'node_remote.svg'},
	{id: 'rf433', name: "{{RF433}}", file: 'node_rf433.svg'},
	{id: 'rfid', name: "{{RFID}}", file: 'node_rfid.png'},
	{id: 'sms', name: "{{SMS}}", file: 'node_sms.png'},
	{id: 'teleinfo', name: "{{Téléinfo}}", file: 'node_teleinfo.png'},
	{id: 'temp', name: "{{Température}}", file: 'node_temp.png'},
	{id: 'thermostat', name: "{{Thermostat}}", file: 'node_thermostat.png'},
	{id: 'tv', name: "{{Télévison}}", file: 'node_tv.svg'},
	{id: 'volet', name: "{{Volet}}", file: 'node_volet.svg'},
	{id: 'water-boiler', name: "{{Chaudière}}", file: 'node_water-boiler.svg'},
	{id: 'wifi', name: "{{Wifi}}", file: 'node_wifi.svg'},
	{id: 'window-closed-variant', name: "{{Fenêtre}}", file: 'node_window-closed-variant.svg'},
	{id: 'zigbee', name: "{{Zigbee}}", file: 'node_zigbee.svg'},
	{id: 'zwave', name: "{{ZWave}}", file: 'node_zwave.svg'}
]
jmqtt.icons.sort(function(a, b) { return a.name.localeCompare(b.name); });

// To memorise page refresh timeout when set
jmqtt.refreshTimeout = null;

// To memorise current eqLogic main subscription topic
jmqtt.mainTopic = '';

jmqtt.checkTopicMismatch = function (item) {
	if (jmqtt.mainTopic == '') { // Nothing matches empty main subscription topic
			item.addClass('topicMismatch');
	} else if (jmqtt.mainTopic == '#') { // Everything matches '#' main subscription topic
			item.removeClass('topicMismatch');
	} else {
		var subRegex = new RegExp(`^${jmqtt.mainTopic}\$`.replaceAll('+', '[^/]*').replace('/#', '(|/.*)'))
		if (!subRegex.test(item.value()))
			item.addClass('topicMismatch');
		else
			item.removeClass('topicMismatch');
	}
}


	var mt = $('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]');
	jmqtt.mainTopic = mt.val();
	if (jmqtt.mainTopic == '')
		mt.addClass('topicMismatch');
	else
		mt.removeClass('topicMismatch');
}

jmqtt.callPluginAjax = function(_params) {
	$.ajax({
		async: _params.async == undefined ? true : _params.async,
		global: false,
		type: "POST",
		url: "plugins/jMQTT/core/ajax/jMQTT.ajax.php",
		data: _params.data,
		dataType: 'json',
		error: function (request, status, error) {
			handleAjaxError(request, status, error);
		},
		success: function (data) {
			if (data.state != 'ok') {
				$.fn.showAlert({message: data.result, level: 'danger'});
			}
			else {
				if (typeof _params.success === 'function') {
					_params.success(data.result);
				}
			}
		}
	});
}

/*
 * Rebuild the page URL from the current URL
 *
 * filter: array of parameters to be removed from the URL
 * id:     if not empty, it is appended to the URL (in that case, 'id' should be passed within the filter.
 * hash:   if provided, it is appended at the end of the URL (shall contain the # character). If a hash was already
 *         present, it is replaced by that one.
 */
jmqtt.initPluginUrl = function(filter=['id', 'saveSuccessFull','removeSuccessFull', 'hash'], id='', hash='') {
	var vars = getUrlVars();
	var url = 'index.php?';
	for (var i in vars) {
		if ($.inArray(i,filter) < 0) {
			if (url.substr(-1) != '?')
				url += '&';
			url += i + '=' + vars[i].replace('#', '');
		}
	};
	if (id != '') {
		url += '&id=' + id;
	}
	if (document.location.hash != "" && $.inArray('hash',filter) < 0) {
		url += document.location.hash;
	}
	if (hash != '' ) {
		url += hash
	}
	return url;
}

/*
 * Function to refresh the page
 * Ask confirmation if the page has been modified
 */
jmqtt.refreshEqLogicPage = function() {
	function refreshPage() {
		if ($('.eqLogicAttr[data-l1key=id]').value() != "") {
			tab = null
			if (document.location.toString().match('#')) {
				tab = '#' + document.location.toString().split('#')[1];
				if (tab != '#') {
					tab = $('a[href="' + tab + '"]')
				} else {
					tab = null
				}
			}
			$('.eqLogicDisplayCard[data-eqlogic_id="' + $('.eqLogicAttr[data-l1key=id]').value() + '"]').click();
			if (tab) tab.click();
		}
		else {
			$('.eqLogicAction[data-action=returnToThumbnailDisplay]').click();
		}
	}
	if (modifyWithoutSave) {
		bootbox.confirm("{{La page a été modifiée. Etes-vous sûr de vouloir la recharger sans sauver ?}}", function (result) {
			if (result)
				refreshPage();
		});
	}
	else
		refreshPage();
}

// TODO MERGE with updateDisplayCard and resplit according to updateDisplayCard TODO
/*
 * Function to update Broker status on a Broker eqLogic page
 */
jmqtt.showMqttClientInfo = function(_eq) {
	var state = 'nok';
	var message = "{{La connexion à ce Broker est désactivée}}";
	var launchable = jmqttDaemonState ? 'ok' : 'nok';
	if (!jmqttDaemonState) { // Daemon is down
		state = 'nok';
		message = "{{Démon non démarré}}";
	} else if (_eq.cache.mqttClientConnected) {
		state = 'ok';
		message = "{{Le Démon jMQTT est correctement connecté à ce Broker}}";
	} else if (_eq.isEnable == '1') {
		state = 'pok';
		message = "{{Le Démon jMQTT n'arrive pas à se connecter à ce Broker}}";
	}
	$('.mqttClientLaunchable span.label').removeClass('label-success label-warning label-danger').text(launchable.toUpperCase());
	switch(launchable) {
		case 'ok':
			$('.eqLogicAction[data-action=startMqttClient]').show();
			$('.mqttClientLaunchable span.label').addClass('label-success');
			break;
		case 'nok':
			$('.eqLogicAction[data-action=startMqttClient]').hide();
			$('.mqttClientLaunchable span.label').addClass('label-danger');
			break;
		default:
			$('.mqttClientLaunchable span.label').addClass('label-warning');
			$('.mqttClientLaunchable span.state').text(' ' + message);
	}
	var color = state == 'ok' ? 'success' : (state == 'nok' ? 'danger' : 'warning');
	$('.mqttClientState span.label').removeClass('label-success label-warning label-danger').addClass('label-' + color).text(state.toUpperCase());
	$('.mqttClientState span.state').text(' ' + message);
	$(".mqttClientPanel").removeClass('panel-success panel-warning panel-danger').addClass('panel-' + color);
	$('.mqttClientLastLaunch').empty().append((_eq.cache.lastLaunchTime == undefined || _eq.cache.lastLaunchTime == '') ? '{{Inconnue}}' : _eq.cache.lastLaunchTime);

	if (state == "ok") {
		brk = $('.eqLogicDisplayCard[jmqtt_type=broker][data-eqlogic_id=' + _eq.id + ']');
		// Update borker on main page and show an alert
		if (_eq.cache.include_mode == '1') {
			if (brk.find('span.hiddenAsTable i.inc-status').hasClass('fa-square')) {
				// Only if Include Mode is already disabled
				brk.find('span.hiddenAsTable i.inc-status').removeClass('far fa-square').addClass('fas fa-sign-in-alt fa-rotate-90');
				brk.find('span.hiddenAsCard i.inc-status').removeClass('far fa-square success').addClass('fas fa-sign-in-alt fa-rotate-90 warning');
				$.fn.showAlert({message: '{{Inclusion automatique sur le Broker }}<b>' + _eq.name + '</b>{{ pendant 3 minutes. Cliquez sur le bouton pour forcer la sortie de ce mode avant.}}', level: 'warning'});
			}
		} else if (brk.find('span.hiddenAsTable i.inc-status').hasClass('fa-sign-in-alt')) {
			// Only if Include Mode is already enabled
			brk.find('span.hiddenAsTable i.inc-status').removeClass('fas fa-sign-in-alt fa-rotate-90').addClass('far fa-square');
			brk.find('span.hiddenAsCard i.inc-status').removeClass('fas fa-sign-in-alt fa-rotate-90 warning').addClass('far fa-square success');
			$.fn.hideAlert();
			$.fn.showAlert({message: '{{Fin de l\'inclusion automatique sur le Broker }}<b>' + _eq.name + '</b>.', level: 'warning'});
		}

		// Set which inclusion button is visible on the right eqBroker
		if (_eq.id == $('.eqLogicAttr[data-l1key=id]').value()) {
			if (_eq.cache.include_mode == '1') { // Include Start
				$('.eqLogicAction[data-action=startIncludeMode]').hide();
				$('.eqLogicAction[data-action=stopIncludeMode]').show();
			} else { // Include Stop
				$('.eqLogicAction[data-action=startIncludeMode]').show();
				$('.eqLogicAction[data-action=stopIncludeMode]').hide();
			}
		}
	}
}

// Inform Jeedom to change include mode
jmqtt.setIncludeMode = function(_id, _mode) {
	// Ajax call to inform the plugin core of the change
	jmqtt.callPluginAjax({
		data: {
			action: "changeIncludeMode",
			mode: _mode,
			id: _id
		}
	});
}

// Eqlogic Icon helper
jmqtt.iconHelper = function(_id) {
	// Broker icon is always the same
	if (_id == 'broker')
		return 'plugins/jMQTT/core/img/node_broker.svg';

	// Search for an icon with this id
	var tmp = jmqtt.icons.find(function (item) { return item.id == _id; });
	// Return path to an image according to id
	return (tmp == undefined) ? 'plugins/jMQTT/core/img/node_.svg' : ('plugins/jMQTT/core/img/' + tmp.file);
}

/*
 * Update display card on plugin main page
 */
jmqtt.updateDisplayCard = function (_card) {
	var _id = _card.attr('data-eqlogic_id');
	jeedom.eqLogic.byId({
		id: _id,
		noCache: true,
		error: function (error) {
			$.fn.showAlert({message: error.message, level: 'warning'});
		},
		success: function(_eq) {
			// Set visibility
			if (_eq.isEnable == '1')
				_card.removeClass('disableCard');
			else
				_card.addClass('disableCard');

			// Set icon
			_card.find('img').attr('src', jmqtt.iconHelper(_eq.configuration.icone));

// TODO cut in 2 specific functions specialized in updating an eqBroker or normal eqLogic

			// Set hiddenAsTable span
			var hiddenAsTable = '';
			if (_eq.configuration.type == 'broker') {
				hiddenAsTable += '<i class="inc-status ' + ((_eq.cache.include_mode == '1') ? 'fas fa-sign-in-alt fa-rotate-90' : 'far fa-square') + '"></i>';
				hiddenAsTable += '<i class="status-circle fas ' + (!jmqttDaemonState ? 'fa-times-circle danger' : (_eq.cache.mqttClientConnected ? 'fa-check-circle success' : (_eq.isEnable == '1' ? 'fa-minus-circle warning' : 'fa-times-circle danger'))) + '"></i>';
			} else if (_eq.cache.include_mode == '1')
				hiddenAsTable += '<i class="fas fa-sign-in-alt fa-rotate-90"></i>';
			hiddenAsTable += '<i class="fas eyed ' + ((_eq.isVisible == '1') ? 'fa-eye' : 'fa-eye-slash') + '"></i>';
			// if (_eq.configuration.type == 'broker')
			_card.find('span.hiddenAsTable').empty().html(hiddenAsTable);

			// Set hiddenAsCard span
			function aitool(icl, tip, acl) {
				if (tip == '')
					return '<a class="btn btn-xs cursor w30' + acl + '"><i class="' + icl + ' w18"></i></a>';
				else
					return '<a class="btn btn-xs cursor w30' + acl + '"><i class="' + icl + ' w18 tooltips" title="' + tip + '"></i></a>';
			}
			var hiddenAsCard = '';
			if (_eq.configuration.type == 'broker') {
				// On Brokers
				(_eq.cache.mqttClientConnected ? 'fa-check-circle success' : (_eq.isEnable == '1' ? 'fa-minus-circle warning' : 'fa-times-circle danger'))
				if (_eq.isEnable != '1') {
					// On disabled Brokers
					hiddenAsCard += aitool('status-circle fas fa-times-circle danger', '{{Connexion au Broker désactivée}}', ' roundedLeft');
					hiddenAsCard += aitool(_eq.isVisible == '1' ? 'fas fa-eye' : 'fas fa-eye-slash', '', '');
					hiddenAsCard += aitool('far fa-square w18', '', '');
				} else {
					// On enabled Brokers
					hiddenAsCard += aitool('status-circle fas ' + (!jmqttDaemonState ? 'fa-times-circle danger' : (_eq.cache.mqttClientConnected ? 'fa-check-circle success' : 'fa-minus-circle warning' )),
										   !jmqttDaemonState ? "{{Le Démon n'est pas démarré}}" : (_eq.cache.mqttClientConnected ? '{{Connection au Broker active}}' : '{{Connexion au Broker en échec}}'), ' roundedLeft');
					hiddenAsCard += aitool(_eq.isVisible == '1' ? 'fas fa-eye success' : 'fas fa-eye-slash warning',
										   _eq.isVisible == '1' ? '{{Broker visible}}' : '{{Broker masqué}}', '');
					hiddenAsCard += aitool('inc-status ' + (_eq.cache.include_mode == '1' ? 'fas fa-sign-in-alt warning fa-rotate-90' : 'far fa-square success'),
										   _eq.cache.include_mode == '1' ? '{{Inclusion automatique activée}}' : '{{Inclusion automatique désactivée}}', '');
				}
				hiddenAsCard += '<a class="btn btn-xs cursor w30">&nbsp;</a>';
				hiddenAsCard += '<a class="btn btn-xs cursor w30">&nbsp;</a>';
			} else {
				// On normal equipements
				if (_eq.isEnable != '1') {
					// On disabled normal equipements
					hiddenAsCard += aitool('fas fa-times danger', '{{Equipement désactivé}}', ' roundedLeft');
					hiddenAsCard += aitool(_eq.isVisible == '1' ? 'fas fa-eye' : 'fas fa-eye-slash', '', '');
					hiddenAsCard += aitool(_eq.configuration.auto_add_cmd == '1' ? 'fas fa-sign-in-alt fa-rotate-90' : 'far fa-square', '', '');
					if (_eq.configuration.battery_cmd == '')
						var bat = 'fas fa-plug';
					else if (_eq.status.batterydanger)
						var bat = 'fas fa-battery-empty';
					else if (_eq.status.batterywarning)
						var bat = 'fas fa-battery-quarter';
					else
						var bat = 'fas fa-battery-full';
					hiddenAsCard += aitool(bat, '', '');
					hiddenAsCard += aitool(_eq.configuration.availability_cmd == '' ? 'far fa-bell' : 'fas fa-bell', '', '');
				} else {
					// On enabled normal equipements
					hiddenAsCard += aitool('fas fa-check success', '{{Equipement activé}}', ' roundedLeft');
					hiddenAsCard += aitool(_eq.isVisible == '1' ? 'fas fa-eye success' : 'fas fa-eye-slash warning',
										   (_eq.isVisible == '1' == 'ok') ? '{{Equipement visible}}' : '{{Equipement masqué}}', '');
					hiddenAsCard += aitool(_eq.configuration.auto_add_cmd == '1' ? 'fas fa-sign-in-alt fa-rotate-90 warning' : 'far fa-square success',
										   _eq.configuration.auto_add_cmd == '1' ? '{{Inclusion automatique activée}}' : '{{Inclusion automatique désactivée}}', '');
					if (_eq.configuration.battery_cmd == '')
						hiddenAsCard += aitool('fas fa-plug', '{{Pas d\'état de la batterie}}', '');
					else if (_eq.status.batterydanger)
						hiddenAsCard += aitool('fas fa-battery-empty danger', '{{Batterie en fin de vie}}', '');
					else if (_eq.status.batterywarning)
						hiddenAsCard += aitool('fas fa-battery-quarter warning', '{{Batterie en alarme}}', '');
					else
						hiddenAsCard += aitool('fas fa-battery-full success', '{{Batterie OK}}', '');
					if (_eq.configuration.availability_cmd == '')
						hiddenAsCard += aitool('far fa-bell', '{{Pas d\'état de disponibilité}}', '');
					else if (_eq.status.warning)
						hiddenAsCard += aitool('fas fa-bell danger', '{{Equipement indisponible}}', '');
					else
						hiddenAsCard += aitool('fas fa-bell success', '{{Equipement disponible}}', '');
				}
			}
			// Add advanced configuration cog
			hiddenAsCard += '<a class="btn btn-xs cursor w30 roundedRight"><i class="fas fa-cogs eqLogicAction tooltips" title="{{Configuration avancée}}" data-action="confEq"></i></a>';
			_card.find('span.hiddenAsCard').empty().html(hiddenAsCard);
		}
	});
}


//
// Utilitary functions
//

// Check if a string is a valid Json, returns json object if true, undefined otherwise
jmqtt.toJson = function(_string) {
	try {
		return JSON.parse(_string);
	} catch (e) {
		return undefined;
	}
}

// Check if a topic matches a subscription, return bool

//
// Actions on main plugin view
//
$('.eqLogicAction[data-action=addJmqttBrk]').off('click').on('click', function () {
	bootbox.prompt("{{Nom du nouveau broker ?}}", function (result) {
		if (result !== null) {
			jeedom.eqLogic.save({
				type: eqType,
				eqLogics: [ $.extend({name: result}, {type: 'broker', eqLogic: -1}) ],
				error: function (error) {
					$.fn.showAlert({message: error.message, level: 'danger'});
				},
				success: function (data) {
					var url = jmqtt.initPluginUrl();
					modifyWithoutSave = false;
					url += '&id=' + data.id + '&saveSuccessFull=1';
					loadPage(url);
				}
			});
		}
	});
});

$('.eqLogicAction[data-action=healthMQTT]').on('click', function () {
	$('#md_modal').dialog({title: "{{Santé jMQTT}}"});
	$('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=health').dialog('open');
});

$('.eqLogicAction[data-action=debugJMQTT]').on('click', function () {
	$('#md_modal').dialog({title: "{{Debug jMQTT}}"});
	$('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=debug').dialog('open');
});

$('.eqLogicAction[data-action=templatesMQTT]').on('click', function () {
	$('#md_modal').dialog({title: "{{Gestion des templates d'équipements}}"});
	$('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=templates').dialog('open');
});

// TODO Move to a new Broker tab
/*
$('.eqLogicAction[data-action=discoveryJMQTT]').on('click', function () {
	$('#md_modal').dialog({title: "{{Découverte automatique}}"});
	$('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=discovery').dialog('open');
});

$('.eqLogicAction[data-action=realTimeJMQTT]').on('click', function () {
	$('#md_modal').dialog({title: "{{Découverte automatique}}"});
	$('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=realtime').dialog('open');
});
*/

$('.eqLogicAction[data-action=addJmqttEq]').off('click').on('click', function () {
	var dialog_message = '<label class="control-label">{{Choisissez un broker : }}</label> ';
	dialog_message += '<select class="bootbox-input bootbox-input-select form-control" id="addJmqttBrkSelector">';
	for(var i in eqBrokers){ dialog_message += '<option value="'+i+'">'+eqBrokers[i]+'</option>'; } // Use global var in jMQTT.php !!!
	dialog_message += '</select><br>';
	dialog_message += '<label class="control-label">{{Nom du nouvel équipement : }}</label> ';
	dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" id="addJmqttEqName"><br><br>'
	bootbox.confirm({
		title: "{{Ajouter un nouvel équipement}}",
		message: dialog_message,
		callback: function (result){ if (result) {
			var broker = $('#addJmqttBrkSelector').value();
			if (broker === undefined || broker == null || broker == '' || broker == false) {
				$.fn.showAlert({message: "{{Broker invalide !}}", level: 'warning'});
				return false;
			}
			var eqName = $('#addJmqttEqName').value();
			if (eqName === undefined || eqName == null || eqName === '' || eqName == false) {
				$.fn.showAlert({message: "{{Le nom de l'équipement ne peut pas être vide !}}", level: 'warning'});
				return false;
			}
			jeedom.eqLogic.save({
				type: eqType,
				eqLogics: [ $.extend({name: eqName}, {type: 'eqpt', eqLogic: broker}) ],
				error: function (error) {
					$.fn.showAlert({message: error.message, level: 'danger'});
				},
				success: function (data) {
					var url = jmqtt.initPluginUrl();
					modifyWithoutSave = false;
					url += '&id=' + data.id + '&saveSuccessFull=1';
					loadPage(url);
				}
			});
		}}
	});
});

$('.eqLogicAction[data-action=confEq]').off('click').on('click', function() {
	var eqId = $(this).closest('div').attr('data-eqLogic_id');
	$('#md_modal').dialog().load('index.php?v=d&modal=eqLogic.configure&eqLogic_id=' + eqId).dialog('open');
});

//
// Modals associated to buttons "Rechercher équipement" for Action and Info Cmd
//
$("#table_cmd").delegate(".listEquipementAction", 'click', function() {
	var el = $(this);
	jeedom.cmd.getSelectModal({cmd: {type: 'action'}}, function(result) {
		var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.attr('data-input') + ']');
		calcul.value(result.human);
	});
});

$("#table_cmd").delegate(".listEquipementInfo", 'click', function () {
	var el = $(this);
	jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
		var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']');
		calcul.atCaret('insert', result.human);
		modifyWithoutSave = true
	});
});

//
// Hide / Show top menu depending of the selected Tab in Eq/Brk
//
$('.nav-tabs a[href="#eqlogictab"],.nav-tabs a[href="#brokertab"],.nav-tabs a[href="#realtimetab"]').on('click', function() {
	$('#menu-bar').hide();
});

$('.nav-tabs a[href="#commandtab"]').on('click', function() {
	if($('.eqLogicAttr[data-l1key="configuration"][data-l2key="type"]').value() != 'broker') {
		$('#menu-bar').show();
	}
});

//
// Actions on Broker tab
//
$('.eqLogicAction[data-action=startIncludeMode]').on('click', function() {
	// Enable include mode for a Broker
	jmqtt.setIncludeMode($('.eqLogicAttr[data-l1key=id]').value(), 1);
});

$('.eqLogicAction[data-action=stopIncludeMode]').on('click', function() {
	// Disable include mode for a Broker
	jmqtt.setIncludeMode($('.eqLogicAttr[data-l1key=id]').value(), 0);
});

$('.eqLogicAction[data-action=startMqttClient]').on('click',function(){
	var id = $('.eqLogicAttr[data-l1key=id]').value();
	if (id == undefined || id == "" || $('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val() != 'broker')
		return;
	jmqtt.callPluginAjax({data: {action: 'startMqttClient', id: id}});
});

$('.eqLogicAction[data-action=modalViewLog]').on('click', function() {
	if($('#md_modal').is(':visible')){
		$('#md_modal2').dialog({title: "{{Log du plugin}}"});
		$("#md_modal2").load('index.php?v=d&modal=log.display&log='+$(this).attr('data-log')+'&slaveId='+$(this).attr('data-slaveId')).dialog('open');
	}
	else{
		$('#md_modal').dialog({title: "{{Log du plugin}}"});
		$("#md_modal").load('index.php?v=d&modal=log.display&log='+$(this).attr('data-log')+'&slaveId='+$(this).attr('data-slaveId')).dialog('open');
	}
});

//
// Automations on Broker tab attributes
//
$('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttProto]').change(function(){
	var val = $(this).val();
	if (val == 'mqtts' || val == 'wss')
		$('#jmqttTls').show();
	else
		$('#jmqttTls').hide();
	if (val == 'ws' || val == 'wss')
		$('.jmqttWS').show();
	else
		$('.jmqttWS').hide();
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttTlsCheck]').change(function(){
	switch ($(this).val()) {
		case 'public':
			$('#jmqttTlsCa').hide();
			break;
		case 'private':
			$('#jmqttTlsCa').show();
			break;
		default:
			$('#jmqttTlsCa').hide();
	}
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttTlsClient]').change(function(){
	if ($(this).value() == '1')
		$('.jmqttTlsClient').show();
	else
		$('.jmqttTlsClient').hide();
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttLwt]').change(function(){
	if ($(this).value() == '1')
		$('.jmqttLwt').show();
	else
		$('.jmqttLwt').hide();
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttInt]').change(function(){
	if ($(this).value() == '1')
		$('.jmqttInt').show();
	else
		$('.jmqttInt').hide();
});

$('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttApi]').change(function(){
	if ($(this).value() == '1')
		$('.jmqttApi').show();
	else
		$('.jmqttApi').hide();
});

//
// Automations on Realtime tab attributes
//
$('#table_realtime').on('click', '.cmdAction[data-action=addTo]', function() {
	console.log('addTo: ', $(this).closest('tr').find('.cmdAttr[data-l1key=topic]').val());
	// TODO Add from Realtime tab
})

jmqtt.newRealTimeCmd = function(_data) {
	var tr = '<tr>';
	tr += '<td class="fitwidth"><span class="cmdAttr">' + (_data.date ? _data.date : '') + '</span></td>';
	tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="topic" style="margin-bottom:5px;" value="' + _data.topic + '" disabled>';
	tr += '<input class="cmdAttr form-control input-sm col-lg-11 col-md-10 col-sm-10 col-xs-10" style="float: right;" data-l1key="jsonPath" value="' + _data.jsonPath + '" disabled></td>';
	tr += '<td><textarea class="cmdAttr form-control input-sm" data-l1key="payload" style="min-height:65px;" readonly=true disabled>' + _data.payload + '</textarea></td>';
	tr += '<td align="center"><input class="cmdAttr form-control" data-l1key="qos" style="display:none;" value="' + _data.qos + '" disabled>';
	tr += '<span class="cmdAttr tooltips" data-l1key="retain" title="{{Ce message est stocké sur le Broker (Retain)}}">' + (_data.retain ? '<i class="fas fa-database warning"></i>' : '') + '</span>';
	if (_data.retain && _data.included)
		tr += '<br /><br />';
	if (_data.included)
		tr += '<i class="fas fa-sign-in-alt fa-rotate-90 success tooltips" title="{{Ce topic est déjà présent sur l\'équipement/commande :}}<br/>' + _data.included + '"></i>';
	tr += '</td><td align="right"><a class="btn btn-success btn-sm roundedLeft cmdAction tooltips" data-action="addTo" title="{{Ajouter à un équipement existant}}"><i class="fas fa-check-circle"></i> {{Ajouter}}</a>';
	if (typeof(jmqtt.toJson(_data.payload)) === 'object')
		tr += '<a class="btn btn-warning btn-sm cmdAction tooltips" title="{{Découper ce json en commandes}}" data-action="splitJson"><i class="fas fa-expand-alt"></i></a>';
	else
		tr += '<a class="btn btn-default disabled btn-sm cmdAction" data-action="splitJson"><i class="fas fa-expand-alt"></i></a>';
	tr += '<a class="btn btn-danger btn-sm roundedRight cmdAction tooltips" data-action="remove" title="{{Supprimer de la vue}}"><i class="fas fa-minus-circle"></i></a></td>';
	tr += '</tr>';
	return tr;
}

$('#table_realtime').on('click', '.cmdAction[data-action=splitJson]', function() {
	$(this).removeClass('btn-warning').addClass('btn-default disabled');
	var tr = $(this).closest('tr');
	var payload = tr.find('.cmdAttr[data-l1key=payload]').text();
	var json = jmqtt.toJson(payload);
	if (typeof(json) !== 'object')
		return;
	var topic = tr.find('.cmdAttr[data-l1key=topic]').val();
	var jsonPath = tr.find('.cmdAttr[data-l1key=jsonPath]').val();
	var qos = tr.find('.cmdAttr[data-l1key=qos]').value();
	tr = tr.next();

	for (item in json) {
		var _data = {topic: topic, payload: JSON.stringify(json[item]), qos: qos, retain: false};
		if (item.match(/[^\w-]/)) // Escape if a special character is found
			item = '\'' + item.replace(/'/g,"\\'") + '\'';
		_data.jsonPath = jsonPath + '[' + item + ']';
		var new_tr = jmqtt.newRealTimeCmd(_data);
		tr.before(new_tr);
	}
})

$('#table_realtime').on('click', '.cmdAction[data-action=remove]', function() {
	modifyWithoutSave = true;
	$(this).closest('tr').remove();
})

/*
 * Update the realtime view of broker if displayed on reception of a new RealTime event
 */
$('body').off('jMQTT::RealTime').on('jMQTT::RealTime', function (_event, _options) {
	if ($('.eqLogicAttr[data-l1key=id]').value() == _options.id) {
		modifyWithoutSave = true;

		var d = new Date();
		_options.date = d.toISOString().slice(0,10) + " " + d.toLocaleTimeString() + "." + d.getMilliseconds();
		_options.jsonPath = '';
		if (d.getMilliseconds() % 2 == 0)
			_options.included = 'tutu tete';
		var tr = jmqtt.newRealTimeCmd(_options);
		$('#table_realtime tbody').prepend(tr);
	}
});

//
// Automations on Equipment tab attributes
//
$('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').on('change', function(e) {
	if($(e.target).value() == 'broker') {
		$('#menu-bar').hide();
	}
});

// On eqLogic subscription topic field typing
$('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').on('input', function() {
	jmqtt.onMainTopicChange();
});

// On eqLogic subscription topic field set (initial set and finish typing)
$('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').on('change', function() {
	jmqtt.onMainTopicChange();
	$('input.cmdAttr[data-l1key=configuration][data-l2key=topic]').each(function() {
		jmqtt.checkTopicMismatch($(this));
	});
});

// On eqLogic subscription topic field typing
$('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').off('dblclick').on('dblclick', function() {
	if($(this).val() == "") {
		var objectname = $('.eqLogicAttr[data-l1key=object_id] option:selected').text();
		var eqName = $('.eqLogicAttr[data-l1key=name]').value();
		$(this).val(objectname.trim()+'/'+eqName+'/#');
		jmqtt.onMainTopicChange();
	}
});

// On eqLogic icon field set (initial set and finish typing)
$('.eqLogicAttr[data-l1key=configuration][data-l2key=icone]').change(function() {
	if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val() == 'broker') {
		$("#icon_visu").attr("src", jmqtt.iconHelper('broker'));
	} else {
		var elt = $('.eqLogicAttr[data-l1key=configuration][data-l2key=icone] option:selected');
		$("#icon_visu").attr("src", jmqtt.iconHelper(elt.val()));
	}
});

// Configure the sortable functionality of the commands array
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

// Restrict "cmd.configure" modal popup when double-click on command without id
$('#table_cmd').on('dblclick', '.cmd[data-cmd_id=""]', function(event) {
	event.stopPropagation()
});


//
// Actions in top menu on an Equipment
//

// On applyTemplate click
$('.eqLogicAction[data-action=applyTemplate]').off('click').on('click', function () {
	jmqtt.callPluginAjax({
		data: {
			action: "getTemplateList",
		},
		success: function (dataresult) {
			var dialog_message = '<label class="control-label">{{Choisissez un template : }}</label> ';
			dialog_message += '<select class="bootbox-input bootbox-input-select form-control" id="applyTemplateSelector">';
			for(var i in dataresult){ dialog_message += '<option value="'+dataresult[i][0]+'">'+dataresult[i][0]+'</option>'; }
			dialog_message += '</select><br>';

			dialog_message += '<label class="control-label">{{Saisissez le Topic de base : }}</label> ';
			var currentTopic = jmqtt.mainTopic;
			if (currentTopic.endsWith("#") || currentTopic.endsWith("+"))
				currentTopic = currentTopic.substr(0,currentTopic.length-1);
			if (currentTopic.endsWith("/"))
				currentTopic = currentTopic.substr(0,currentTopic.length-1);
			dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" id="applyTemplateTopic" value="'+currentTopic+'"><br><br>'

			dialog_message += '<label class="control-label">{{Que voulez-vous faire des commandes existantes ?}}</label> ' +
			'<div class="radio">' +
			'<label><input type="radio" name="applyTemplateCommand" value="1" checked="checked">{{Les conserver / Mettre à jour}}</label>' +
			'</div><div class="radio">' +
			'<label><input type="radio" name="applyTemplateCommand" value="0">{{Les supprimer d\'abord}}</label> ' +
			'</div>';

			bootbox.confirm({
				title: '{{Appliquer un Template}}',
				message: dialog_message,
				callback: function (result){ if (result) {
					jmqtt.callPluginAjax({
						data: {
							action: "applyTemplate",
							id: $('.eqLogicAttr[data-l1key=id]').value(),
							name : $("#applyTemplateSelector").val(),
							topic: $("#applyTemplateTopic").val(),
							keepCmd: $("[name='applyTemplateCommand']:checked").val()
						},
						success: function (dataresult) {
							$('.eqLogicDisplayCard[data-eqLogic_id='+$('.eqLogicAttr[data-l1key=id]').value()+']').click();
						}
					});
				}}
			});
		}
	});
});

// On createTemplate click
$('.eqLogicAction[data-action=createTemplate]').off('click').on('click', function () {
	bootbox.prompt({
		title: "{{Nom du nouveau template ?}}",
		callback: function (result) {
			if (result !== null) {
				jmqtt.callPluginAjax({
					data: {
						action: "createTemplate",
						id: $('.eqLogicAttr[data-l1key=id]').value(),
						name : result
					},
					success: function (dataresult) {
						$('.eqLogicDisplayCard[data-eqLogic_id='+$('.eqLogicAttr[data-l1key=id]').value()+']').click();
					}
				});
			}
		}
	});
});

// On updateTopics click
$('.eqLogicAction[data-action=updateTopics]').off('click').on('click', function () {
	var dialog_message = '<label class="control-label">{{Rechercher :}}</label> ';
	var currentTopic = jmqtt.mainTopic
	if (currentTopic.endsWith("#") || currentTopic.endsWith("+"))
		currentTopic = currentTopic.substr(0,currentTopic.length-1);
	if (currentTopic.endsWith("/"))
		currentTopic = currentTopic.substr(0,currentTopic.length-1);
	dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" id="oldTopic" value="'+currentTopic+'"><br><br>';
	dialog_message += '<label class="control-label">{{Replacer par :}}</label> ';
	dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" type="text" id="newTopic"><br><br>';
	dialog_message += '<label class="control-label">({{Pensez à sauvegarder l\'équipement pour appliquer les modifications}})</label>';
	bootbox.confirm({
		title: "{{Modifier en masse les Topics de tout l'équipement}}",
		message: dialog_message,
		callback: function (valid){ if (valid) {
			var oldTopic = $("#oldTopic").val();
			var newTopic = $("#newTopic").val();
			var mainTopic = $('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]');
			if (mainTopic.val().startsWith(oldTopic))
				mainTopic.val(mainTopic.val().replace(oldTopic, newTopic));
			$('.cmdAttr[data-l1key=configuration][data-l2key=topic]').each(function() {
				if ($(this).val().startsWith(oldTopic))
					$(this).val($(this).val().replace(oldTopic, newTopic));
			});
			modifyWithoutSave = true;
		}}
	});
});

// On addMQTTInfo click
$('.eqLogicAction[data-action=addMQTTInfo]').on('click', function() {
	var _cmd = {type: 'info'};
	addCmdToTable(_cmd);
	modifyWithoutSave = true;
});

// On addMQTTAction click
$('.eqLogicAction[data-action=addMQTTAction]').on('click', function() {
	var _cmd = {type: 'action'};
	addCmdToTable(_cmd);
	modifyWithoutSave = true;
});

// On classicView click
$('.eqLogicAction[data-action=classicView]').on('click', function() {
	jmqtt.refreshEqLogicPage();
	$('.eqLogicAction[data-action=classicView]').removeClass('btn-default').addClass('btn-primary');
	$('.eqLogicAction[data-action=jsonView]').removeClass('btn-primary').addClass('btn-default');
});

// On jsonView click
$('.eqLogicAction[data-action=jsonView]').on('click', function() {
	jmqtt.refreshEqLogicPage();
	$('.eqLogicAction[data-action=jsonView]').removeClass('btn-default').addClass('btn-primary');
	$('.eqLogicAction[data-action=classicView]').removeClass('btn-primary').addClass('btn-default');
});

/**
 * printEqLogic callback called by plugin.template before calling addCmdToTable.
 *   . Reorder commands if the JSON view is active
 *   . Show the fields depending on the type (broker or equipment)
 */
function printEqLogic(_eqLogic) {

	// Initialize the counter of the next root level command to be added
	var n_cmd = 1;

	// Is the JSON view is active
	var is_json_view = $('.eqLogicAction[data-action=jsonView].active').length != 0;

	// JSON view button is active
	if (is_json_view) {

		// Compute the ordering string of each commands
		// On JSON view, we completely rebuild the command table
		var new_cmds = new Array();

		/**
		 * Add a command to the JSON commands tree
		 * @return tree_id of the added cmd
		 */
		function addCmd(c, parent_id='') {
			if (parent_id !== '') {
				c.tree_parent_id = parent_id;
				var m_cmd = 0;
				//we need to find existing childrens of this parent
				//and find higher existing number
				new_cmds.forEach(function (c) {
					if (c.tree_parent_id === parent_id) {
						var id_number = parseInt(c.tree_id.substring(parent_id.length + 1)); // keep only end part of id and parse it
						if (id_number > m_cmd) m_cmd = id_number;
					}
				});
				c.tree_id = parent_id + '.' + (m_cmd + 1).toString();
			}
			else {
				c.tree_id = (n_cmd++).toString();
			}
			new_cmds.push(c);
			return c.tree_id;
		}

		/**
		 * Check if the given command is in the given array
		 * @return found command or undefined
		 */
		function inArray(cmds, cmd) {
			return cmds.find(function (c) { return c == cmd });
		}

		/**
		 * Check if the given topic+jsonPath is in the given array
		 * @return found command or undefined
		 */
		function existingCmd(cmds, topic, jsonPath) {
			// try to find cmd that match with topic and jsonPath (or jsonPath with dollar sign in front)
			var exist_cmds = cmds.filter(function (c) { return c.configuration.topic == topic && (c.configuration.jsonPath == jsonPath || c.configuration.jsonPath == '$' + jsonPath); });
			if (exist_cmds.length > 0)
				return exist_cmds[0];
			else
				return undefined;
		}

		/**
		 * Add the given topic/jsonPath/payload to the command array.
		 * If the command already exists, add the existing command. Otherwise create a no name command.
		 * @return tree_id of the added payload
		 */
		function addPayload(topic, jsonPath, payload, parent_id) {
			var val = (typeof payload === 'object') ? JSON.stringify(payload) : payload;
			var c =  existingCmd(_eqLogic.cmd, topic, jsonPath);
			//console.log('addPayload: topic=' + topic + ', jsonPath=' + jsonPath + ', payload=' + val + ', parent_id=' + parent_id + ', exist=' + (c == undefined ? false : true));
			if (c === undefined) {
				return addCmd({
					configuration: {
						topic: topic,
						jsonPath: jsonPath
					},
					isHistorized: "0",
					isVisible: "1",
					type: 'info',
					subType: 'string',
					value: val
				}, parent_id);
			}
			else {
				c.value = val;
				return addCmd(c, parent_id);
			}
		}

		/**
		 * Add to the JSON command tree the given command identified by its topic, jsonPath and JSON payload
		 * plus the commands deriving from the JSON payload
		 */
		function recursiveAddJsonPayload(topic, jsonPath, payload, parent_id='') {
			//console.log('recursiveAddJsonPayload: topic=' + topic + ', jsonPath=' + jsonPath + ', payload=' + JSON.stringify(payload));
			var this_id = addPayload(topic, jsonPath, payload, parent_id);
			for (i in payload) {
				var escapedi = i;
				if (escapedi.match(/[^\w-]/)) { // Escape if a special character is found
					escapedi = '\'' + escapedi.replace(/'/g,"\\'") + '\'';
				}
				if (typeof payload[i] === 'object') {
					recursiveAddJsonPayload(topic, jsonPath + '[' + escapedi + ']', payload[i], this_id);
				}
				else {
					addPayload(topic, jsonPath + '[' + escapedi + ']', payload[i], this_id);
				}
			}
		}

		/**
		 * Add commands from their topic
		 */
		function recursiveAddCmdFromTopic(topic, jsonPath) {
			//console.log('recursiveAddCmdFromTopic: ' + topic + jsonPath);
			var parent_id = '';

			// For commands deriving from a JSON payload (i.e. jsonPath is not undefined or empty),
			// start the addition from the father command
			if (jsonPath) {
				// Call recursively this method with the topic and no jsonPath
				recursiveAddCmdFromTopic(topic, '');
				// We need to get the tree id of the father command to be able to add this command to tree in the next step
				var c = existingCmd(new_cmds, topic, '');
				if (c !== undefined)
					parent_id = c.tree_id;
			}

			// Add this command to the tree if not previously added
			var c = existingCmd(new_cmds, topic, jsonPath);
			if (c === undefined) {
				c = existingCmd(_eqLogic.cmd, topic, jsonPath);
				if (c !== undefined) {
					// Get the payload associated to the command
					jeedom.cmd.execute({
						async: false, id: c.id, cache: 0, notify: false,
						success: function(result) {
							c.value = result;
						}});
					try {
						var parsed_json_value = JSON.parse(c.value);
					}
					catch (e) {}

					// Add the command: in case of JSON payload, call recursiveAddJsonPayload to add
					// also the derived commands
					if (typeof parsed_json_value === 'object') {
						recursiveAddJsonPayload(c.configuration.topic, c.configuration.jsonPath, parsed_json_value, parent_id);
					}
					else {
						addCmd(c, parent_id);
					}
				}
			}
		}

		// Main loop on the existing command: objective is to add to the JSON command tree all the
		// existing commands plus the commands that can be created from JSON payloads
		for (var c of _eqLogic.cmd) {
			if (!inArray(new_cmds, c)) {
				if (c.type == 'info') {
					//console.log('loop: add info ' + c.configuration.topic + c.configuration.jsonPath);
					recursiveAddCmdFromTopic(c.configuration.topic, c.configuration.jsonPath);
				}
				else {
					// Action commands are added directly
					addCmd(c);
				}
			}
		}

		_eqLogic.cmd = new_cmds;

		// JSON view: disable the sortable functionality
		$("#table_cmd").sortable('disable');
	} else {
		// CLASSIC view button is active
		for (var c of _eqLogic.cmd) {
			c.tree_id = (n_cmd++).toString();
		}

		// Classical view: enable the sortable functionality
		$("#table_cmd").sortable('enable');
	}

	// Show UI elements depending on the type
	if ((_eqLogic.configuration.type == 'eqpt' && (_eqLogic.configuration.eqLogic == undefined || _eqLogic.configuration.eqLogic < 0))
			|| (_eqLogic.configuration.type != 'eqpt' && _eqLogic.configuration.type != 'broker')) { // Unknow EQ / orphan
		$('.toDisable').addClass('disabled');
		$('.typ-brk').hide();
		$('.typ-std').show();
	}
	else if (_eqLogic.configuration.type == 'broker') { // jMQTT Broker
		$('.toDisable').removeClass('disabled');
		$('.typ-std').hide();
		$('.typ-brk').show();
		if (_eqLogic.isEnable != '1') {
			$('.eqLogicAction[data-action=startIncludeMode]').show().addClass('disabled');
			$('.eqLogicAction[data-action=stopIncludeMode]').hide();
		} else if (_eqLogic.cache.include_mode != '1') {
			$('.eqLogicAction[data-action=startIncludeMode]').show().removeClass('disabled');
			$('.eqLogicAction[data-action=stopIncludeMode]').hide();
		} else {
			$('.eqLogicAction[data-action=startIncludeMode]').show().hide();
			$('.eqLogicAction[data-action=stopIncludeMode]').addClass('roundedLeft');
		}
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').prop('readonly', true);
		var log = 'jMQTT_' + (_eqLogic.name.replace(' ', '_') || 'jeedom');
		$('input[name=rd_logupdate]').attr('data-l1key', 'log::level::' + log);
		$('.eqLogicAction[data-action=modalViewLog]').attr('data-log', log);
		$('.eqLogicAction[data-action=modalViewLog]').html('<i class="fas fa-file-text-o"></i> ' + log);
		var levels = {};
		levels['log::level::' + log] = _eqLogic.configuration.loglevel
		$('#div_broker_log').setValues(levels, '.configKey');

		// Udpate other stuff on eqBroker
		jmqtt.showMqttClientInfo(_eqLogic);
	}
	else if (_eqLogic.configuration.type == 'eqpt') { // jMQTT Eq
		$('.toDisable').removeClass('disabled');
		$('.typ-brk').hide();
		$('.typ-std').show();
		$('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').prop('readonly', false);

		jmqtt.mainTopic = $('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').val();
		// Initialise battery and availability dropboxes
		var eqId = $('.eqLogicAttr[data-l1key=id]').value();
		var bat = $('.eqLogicAttr[data-l1key=configuration][data-l2key=battery_cmd]');
		var avl = $('.eqLogicAttr[data-l1key=configuration][data-l2key=availability_cmd]');
		bat.empty().append('<option value="">{{Aucune}}</option>');
		avl.empty().append('<option value="">{{Aucune}}</option>');
		jeedom.eqLogic.buildSelectCmd({
			id: eqId,
			filter: {type: 'info', subType: 'numeric'},
			error: function (error) {
				$.fn.showAlert({message: error.message, level: 'danger'});
			},
			success: function (result) {
				bat.append(result);
			}
		});
		jeedom.eqLogic.buildSelectCmd({
			id: eqId,
			filter: {type: 'info', subType: 'binary'},
			error: function (error) {
				$.fn.showAlert({message: error.message, level: 'danger'});
			},
			success: function (result) {
				avl.append(result);
				bat.append(result); // Also append binary cmd to battery dropbox
			}
		});
		bat.val(_eqLogic.configuration.battery_cmd);
		avl.val(_eqLogic.configuration.availability_cmd);
	}

	// Initialize the broker dropbox
	var brokers = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqLogic]');
	brokers.empty();
	$.each(eqBrokers, function(key, name) {
		brokers.append(new Option(name, key));
	});
	brokers.val(_eqLogic.configuration.eqLogic);
}

/**
 * saveEqLogic callback called by plugin.template before saving an eqLogic
 */
function saveEqLogic(_eqLogic) {
	if (_eqLogic.configuration.type != 'broker' && _eqLogic.configuration.type != 'eqpt') {
		// not on an jMQTT eqLogic, to fix issue #153
		return _eqLogic;
	}
	// pass the log level when defined for a broker object
	if (_eqLogic.configuration.type == 'broker') {
		var log_level = $('#div_broker_log').getValues('.configKey')[0];
		if (!$.isEmptyObject(log_level)) {
			_eqLogic.loglevel =  log_level;
		}
	}

	// remove non existing commands added for the JSON view and add new commands at the end
	for(var i = _eqLogic.cmd.length - 1; i >= 0; i--) {
		if (_eqLogic.cmd[i].id == "" && _eqLogic.cmd[i].name == "") {
			_eqLogic.cmd.splice(i, 1);
		}
	}

	// a function that substract properties of b from a (r = a - b)
	function substract(a, b) {
		var r = {};
		for (var key in a) {
			if (typeof(a[key]) == 'object') {
				if (b[key] === undefined) b[key] = {};
				r[key] = substract(a[key], b[key]);
			} else {
				if (a[key] !== undefined && b[key] === undefined) {
					r[key] = a[key];
				}
			}
		}
		return r;
	}

	// if this eqLogic is not a broker
	if (_eqLogic.configuration.type != 'broker') {
		// get hidden settings for Broker and remove them of eqLogic
		_eqLogic = substract(_eqLogic, $('#brokertab').getValues('.eqLogicAttr')[0]);
	}

	return _eqLogic;
}

/**
 * addCmdToTable callback called by plugin.template: render eqLogic commands
 */
function addCmdToTable(_cmd) {
	const indent_size = 16;
	const expander_expanded_class = 'fas fa-minus';
	const expander_collapsed_class = 'fas fa-plus';

	// Set _cmd and config if empty
	if (!isset(_cmd))
		var _cmd = {configuration: {}};
	if (!isset(_cmd.configuration))
		_cmd.configuration = {};

	// Is the JSON view is active
	var is_json_view = $('.eqLogicAction[data-action=jsonView].active').length != 0;

	if (!isset(_cmd.tree_id)) {
		//looking for all tree-id, keep part before the first dot, convert to Int
		var root_tree_ids = $('[tree-id]').map((pos,e) => parseInt(e.getAttribute("tree-id").split('.')[0]))

		//if some tree-id has been found
		if (root_tree_ids.length > 0) {
			_cmd.tree_id = (Math.max.apply(null, root_tree_ids) + 1).toString(); //use the highest one plus one
		} else {
			_cmd.tree_id = '1'; // else this is the first one
		}
	}

	if (init(_cmd.type) == 'info') {
// TODO: FIXME: is this disabled variable usefull? virtualAction never exists
		var disabled = (init(_cmd.configuration.virtualAction) == '1') ? 'disabled' : '';

		var tr = '<tr class="cmd" tree-id="' + _cmd.tree_id + '"';
		if (is_json_view) {
			if (_cmd.tree_parent_id !== undefined) {
				tr += ' tree-parent-id="' + _cmd.tree_parent_id + '"';
			}
		}
		tr += ' data-cmd_id="' + init(_cmd.id) + '" style="display: none;">'; // SPEED Improvement : Create TR hiden then show it at the end after setValues, etc.
		tr += '<td class="fitwidth">';

		// Add Indent block
		if (is_json_view) {
			var tree_level = (_cmd.tree_id.match(/\./g) || []).length
			tr += '<span class="tree-indent" style="display:inline-block; width: ' + (tree_level*indent_size).toString() + 'px;"></span>';
			tr += '<span class="tree-expander" style="display:inline-block; width: ' + (indent_size).toString() + 'px;"></span>';

			// TRICK: For the JSON view include the "order" value in a hidden element
			// so that the original/natural order is kept when saving
			tr += '<span style="display:none;" class="cmdAttr" data-l1key="order"></span>';
		}
		tr += '<span class="cmdAttr" data-l1key="id"></span>';
		tr += '</td>';

		tr += '<td>';
		tr += '<div class="input-group">';
		tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
		tr += '<span class="input-group-btn">';
		tr += '<a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a>';
		tr += '</span>';
		tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>';
		tr += '</div>';
		tr += '</td>';
		tr += '<td>';
		tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom:5px;width:120px;" />';
		tr += '<span class="cmdAttr subType" subType="' + init(_cmd.subType) + '"></span>';
		tr += '</td><td>';
		tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" placeholder="{{Topic}}" style="margin-bottom:5px;" ' + disabled + '>';
		tr += '<input class="cmdAttr form-control input-sm col-lg-11 col-md-10 col-sm-10 col-xs-10" style="float: right;" data-l1key="configuration" data-l2key="jsonPath" placeholder="{{Chemin JSON}}" '+ disabled + '>';
		tr += '</td><td>';
		tr += '<textarea class="form-control input-sm" data-key="value" style="min-height:65px;" ' + disabled + ' placeholder="{{Valeur}}" readonly=true></textarea>';
		tr += '</td><td>';
		tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:50px;display:inline-block;">';
		tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:50px;display:inline-block;">';
		tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:50px;display:inline-block;margin-right:5px;">';
		tr += '</td><td>';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span><br> ';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span><br> ';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span><br> ';
		tr += '</td><td align="right">';
// TODO Change when adding Advanced parameters
		// tr += '<a class="btn btn-default btn-xs cmdAction tooltips" data-action="advanced" title="{{Paramètres avancés}}"><i class="fas fa-wrench"></i></a> ';
		if (is_numeric(_cmd.id)) {
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
		}
		tr += '&nbsp; &nbsp; <i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
		tr += '</td></tr>';

		$('#table_cmd tbody').append(tr);

		// Update mismatch status of this cmd on change and input
		$('#table_cmd [tree-id="' + _cmd.tree_id + '"] .cmdAttr[data-l1key=configuration][data-l2key=topic]').on('change input', function(e) {
			jmqtt.checkTopicMismatch($(this));
		});

		// Set cmdAttr values of cmd from json _cmd
		$('#table_cmd [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
		if (isset(_cmd.type))
			$('#table_cmd [tree-id="' + _cmd.tree_id + '"] .cmdAttr[data-l1key=type]').value(init(_cmd.type));
		jeedom.cmd.changeType($('#table_cmd [tree-id="' + _cmd.tree_id + '"]'), init(_cmd.subType));

		// Fill in value of current cmd. Efficient in JSON view only as _cmd.value was set in JSON view only in printEqLogic.
		if (is_json_view) {
			$('#table_cmd [tree-id="' + _cmd.tree_id + '"] .form-control[data-key=value]').value(_cmd.value);
		}

		// Get and display the value in CLASSIC view (for JSON view, see few lines above)
		if (_cmd.id != undefined) {
			if (! is_json_view) {
				jeedom.cmd.execute({
					id: _cmd.id,
					cache: 0,
					notify: false,
					success: function(result) {
						$('#table_cmd [tree-id="' + _cmd.tree_id + '"][data-cmd_id="' + _cmd.id + '"] .form-control[data-key=value]').value(result);
				}});
			}

			// Set the update value callback
			jeedom.cmd.update[_cmd.id] = function(_options) {
				$('#table_cmd [tree-id="' + _cmd.tree_id + '"][data-cmd_id="' + _cmd.id + '"] .form-control[data-key=value]').addClass('modifiedVal').value(_options.display_value);
				setTimeout(function() { $('#table_cmd [tree-id="' + _cmd.tree_id + '"][data-cmd_id="' + _cmd.id + '"] .form-control[data-key=value]').removeClass('modifiedVal'); }, 1500 );
			}
		}

		$('#table_cmd [tree-id="' + _cmd.tree_id + '"]').show(); // SPEED Improvement : Create TR hiden then show it at the end after setValues, etc.
	}

	if (init(_cmd.type) == 'action') {
// TODO: FIXME: is this disabled variable usefull? Re-added to avoid "undefined" error
		var disabled = '';

		var tr = '<tr class="cmd" tree-id="' +  _cmd.tree_id + '" data-cmd_id="' + init(_cmd.id) + '" style="display: none;">'; // SPEED Improvement : Create TR hiden then show it at the end after setValues, etc.
		tr += '<td class="fitwidth">';
		tr += '<span class="cmdAttr" data-l1key="id"></span>';
		tr += '</td>';
		tr += '<td>';
		tr += '<div class="input-group">';
		tr += '<input class="cmdAttr form-control input-sm roundedLeft" data-l1key="name" placeholder="{{Nom de la commande}}">';
		tr += '<span class="input-group-btn">';
		tr += '<a class="cmdAction btn btn-sm btn-default" data-l1key="chooseIcon" title="{{Choisir une icône}}"><i class="fas fa-icons"></i></a>';
		tr += '</span>';
		tr += '<span class="cmdAttr input-group-addon roundedRight" data-l1key="display" data-l2key="icon" style="font-size:19px;padding:0 5px 0 0!important;"></span>';
		tr += '</div>';
		tr += '<select class="cmdAttr form-control input-sm" data-l1key="value" style="display:none;margin-top:5px;" title="{{Commande information liée}}">';
		tr += '<option value="">{{Aucune}}</option>';
		tr += '</select>';
		tr += '</td>';
		tr += '<td>';
		tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom:5px;width:120px;" />';
		tr += '<span class="cmdAttr subType" subType="' + init(_cmd.subType) + '" style=""></span>';
		tr += '</td>';
		tr += '<td>';
		tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" style="min-height:62px;margin-top:14px;"' + disabled + ' placeholder="{{Topic}}"></textarea><br/>';
		tr += '</td><td>';
		tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="height:18px;" ' + disabled + ' placeholder="{{Valeur}}"></textarea>';
		tr += '<a class="btn btn-default btn-sm cursor listEquipementInfo" data-input="request" style="margin-top:5px;"><i class="fas fa-list-alt "></i> {{Rechercher équipement}}</a>';
		tr +='</select></span>';
		tr += '</td><td>';
		tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:50px;display:inline-block;">';
		tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:50px;display:inline-block;">';
		tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de valeur|texte séparé par ;}}" title="{{Liste}}">';
		tr += '</td><td>';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span><br> ';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="retain"/>{{Retain}}</label></span><br> ';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="autoPub"/>{{Pub. auto}} <sup><i class="fas fa-question-circle tooltips" title="{{Publication automatique en MQTT lors d\'un changement <br>(Utiliser avec au moins une commande info dans Valeur).}}"></i></sup></label></span><br> ';
		tr += '<span class="checkbox-inline">{{Qos}}: <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="Qos" placeholder="{{Qos}}" title="{{Qos}}" style="width:50px;display:inline-block;"></span> ';
		tr += '</td>';
		tr += '<td align="right">';
// TODO Change when adding Advanced parameters
		// tr += '<a class="btn btn-default btn-xs cmdAction tooltips" data-action="advanced" title="{{Paramètres avancés}}"><i class="fas fa-wrench"></i></a> ';
		if (is_numeric(_cmd.id)) {
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
		}
		tr += '&nbsp; &nbsp; <i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
		tr += '</td></tr>';

		$('#table_cmd tbody').append(tr);
		// $('#table_cmd [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
		var tr = $('#table_cmd [tree-id="' + _cmd.tree_id + '"]');
		jeedom.eqLogic.buildSelectCmd({
			id: $('.eqLogicAttr[data-l1key=id]').value(),
			filter: {type: 'info'},
			error: function (error) {
				$.fn.showAlert({message: error.message, level: 'danger'});
			},
			success: function (result) {
				tr.find('.cmdAttr[data-l1key=value]').append(result);
				tr.setValues(_cmd, '.cmdAttr');
				jeedom.cmd.changeType(tr, init(_cmd.subType));
			}
		});

		$('#table_cmd [tree-id="' + _cmd.tree_id + '"]').show(); // SPEED Improvement : Create TR hiden then show it at the end after setValues, etc.
	}

	if (is_json_view) {
		// add event on expander click
		$('#table_cmd [tree-id="' + _cmd.tree_id + '"] .tree-expander').click(function() {
			var $this = $(this); // "this" but in jQuery
			var tree_id = this.parentNode.parentNode.getAttribute('tree-id'); // find tree-id in TR (2 DOM level up)

			if ($this.hasClass(expander_expanded_class)) { // if expanded
				$this.removeClass(expander_expanded_class).addClass(expander_collapsed_class);

				$('#table_cmd [tree-parent-id^="' + tree_id + '"]').hide(); // hide all childs and sub-childs
			} else if ($this.hasClass(expander_collapsed_class)) { // if collapsed

				$this.removeClass(expander_collapsed_class).addClass(expander_expanded_class);

				/**
				 * Display childs if their own parent are expanded
				 */
				function recursiveDisplayChilds(tree_parent_id) {
					// if parent is expanded
					if ($('#table_cmd [tree-id="' + tree_parent_id + '"] .tree-expander').hasClass(expander_expanded_class)) {
						// for each direct child
						$('#table_cmd [tree-parent-id="' + tree_parent_id + '"]').each(function () {
							// show
							$(this).show();
							// process child of it
							recursiveDisplayChilds(this.getAttribute('tree-id'));
						});
					}
				}

				recursiveDisplayChilds(tree_id);
			}
		});

		//if there is a parent_id, we need to enable his expander
		if(_cmd.tree_parent_id !== undefined){
			$('#table_cmd [tree-id="' + _cmd.tree_parent_id + '"] .tree-expander').addClass(expander_expanded_class);
		}
	}
}

/*
// TODO replace jMQTT::EventState, and change visual on dashboard
$('body').off('jMQTT::brkEvent').on('jMQTT::brkEvent', function (_event,_options) {
	var msg = '{{La commande}} <b>' + _options['name'] + '</b> {{vient d\'être }}' + _options['action'];
	console.log(msg, _options);
});

// TODO replace eqptAdded and handle modified + removed, and change visual on dashboard
$('body').off('jMQTT::eqptEvent').on('jMQTT::eqptEvent', function (_event,_options) {
	var msg = '{{L\'équipement}} <b>' + _options['name'] + '</b> {{vient d\'être}}' + _options['action'];
	console.log(msg, _options);
});

// TODO replace cmdAdded and handle modified + removed
$('body').off('jMQTT::cmdEvent').on('jMQTT::cmdEvent', function (_event,_options) {
	var msg = '{{La commande}} <b>' + _options['name'] + '</b> {{vient d\'être }}' + _options['action'];
	console.log(msg, _options);
});
*/

/**
 * Called by the plugin core to inform about the inclusion of an equipment
 *
 * @param {string} _event event name (jMQTT::eqptAdded in this context)
 * @param {string} _options['eqlogic_name'] string name of the eqLogic command is added to
 */
$('body').off('jMQTT::eqptAdded').on('jMQTT::eqptAdded', function (_event, _options) {
	var msg = '{{L\'équipement}} <b>' + _options['eqlogic_name'] + '</b> {{vient d\'être ajouté}}';

	// If the page is being modified or an equipment is being consulted or a dialog box is shown: display a simple alert message
	// Otherwise: display an alert message and reload the page
	if (modifyWithoutSave || $('.eqLogic').is(":visible") || $('div[role="dialog"]').filter(':visible').length != 0) {
		$.fn.showAlert({message: msg + '.', level: 'warning'});
	}
	else {
		$.fn.showAlert({
			message: msg + '. {{La page va se réactualiser automatiquement}}.',
			level: 'warning'
		});
		// Reload the page after a delay to let the user read the message
		if (jmqtt.refreshTimeout === undefined) {
			jmqtt.refreshTimeout = setTimeout(function() {
				jmqtt.refreshTimeout = undefined;
				window.location.reload();
			}, 3000);
		}
	}
});

/**
 * Management of the display when an information command is added
 * Triggerred when the plugin core send a jMQTT::cmdAdded event
 * @param _event string event name
 * @param _options['eqlogic_name'] string name of the eqLogic command is added to
 * @param _options['eqlogic_id'] int id of the eqLogic command is added to
 * @param _options['cmd_name'] string name of the new command
 * @param _options['reload'] bool whether or not a reload of the page is requested
 */
$('body').off('jMQTT::cmdAdded').on('jMQTT::cmdAdded', function(_event, _options) {
	var msg = '{{La commande}} <b>' + _options['cmd_name'] + '</b> {{est ajoutée à l\'équipement}}' + ' <b>' + _options['eqlogic_name'] + '</b>.';

	// If the page is being modified or another equipment is being consulted or a dialog box is shown: display a simple alert message
	if (modifyWithoutSave || ( $('.eqLogic').is(":visible") && $('.eqLogicAttr[data-l1key=id]').value() != _options['eqlogic_id'] ) ||
			$('div[role="dialog"]').filter(':visible').length != 0 || !_options['reload']) {
		$.fn.showAlert({message: msg, level: 'warning'});
	}
	// Otherwise: display an alert message and reload the page
	else {
		$.fn.showAlert({
			message: msg + ' {{La page va se réactualiser automatiquement}}.',
			level: 'warning'
		});
		// Reload the page after a delay to let the user read the message
		if (jmqtt.refreshTimeout === undefined) {
			jmqtt.refreshTimeout = setTimeout(function() {
				jmqtt.refreshTimeout = undefined;
				$('.eqLogicAction[data-action=refreshPage]').click();
			}, 3000);
		}
	}
});

/*
 * Update the broker icon and the include mode activation on reception of a new state event
 */
$('body').off('jMQTT::EventState').on('jMQTT::EventState', function (_event, _id) {
	jmqtt.updateDisplayCard($('.eqLogicDisplayCard[jmqtt_type="broker"][data-eqlogic_id="' + _id + '"]'));
});

$('body').off('jMQTT::EventDaemonState').on('jMQTT::EventDaemonState', function (_event, _options) {
	jmqttDaemonState = _options;
});

/*
 * Apply some changes when document is loaded
 */
$(document).ready(function() {
	// On page load, show the commandtab menu bar if necessary (fix #64)
	if (document.location.hash == '#commandtab' && $('.eqLogicAttr[data-l1key="configuration"][data-l2key="type"]').value() != 'broker') {
		$('#menu-bar').show();
	}

	// Done here, otherwise the refresh button remains selected
	$('.eqLogicAction[data-action=refreshPage]').removeAttr('href').off('click').on('click', function(event) {
		event.stopPropagation();
		jmqtt.refreshEqLogicPage();
	});

	// Initialize Icon dropbox from jmqtt.icons global table
	var icos = $('.eqLogicAttr[data-l1key=configuration][data-l2key=icone]');
	icos.html('');
	$.each(jmqtt.icons, function(key) {
		opt = new Option(jmqtt.icons[key]['name'], jmqtt.icons[key]['id']);
		icos.append(opt);
	});

	//
	// update DisplayCards on main page at load
	//
	$('.eqLogicDisplayCard').each(function () {
		jmqtt.updateDisplayCard($(this));
	});

	/*
	 * Missing stopPropagation for span.hiddenAsCard in plugin main view
	 * Without this, it is impossible to click on a link in table view without entering the equipement
	 */
	$('.eqLogicDisplayCard').on('click', 'span.hiddenAsCard', function(event) {
		event.stopPropagation()
	});

	// Wrap plugin.template save action handler
	var core_save = $._data($('.eqLogicAction[data-action=save]')[0], 'events')['click'][0]['handler'];
	$('.eqLogicAction[data-action=save]').off('click').on('click', function() {
		// Alert user that there is N mismatch before saveEqLogic (on eqLogic, not on eqBroker)
		if ($('.eqLogicAttr[data-l1key="configuration"][data-l2key="type"]').value() != 'broker' && $('.topicMismatch').length > 0) {
			var dialog_message = '';
			var no_name = false;
			if (jmqtt.mainTopic == '')
				dialog_message += "{{Le topic principal de l'équipement (topic de souscription MQTT) est <b>vide</b> !}}<br>";
			else {
				dialog_message += "{{Le topic principal de l'équipement (topic de souscription MQTT) est}} \"<b>" + jmqtt.mainTopic + '</b>"<br>{{Les commandes suivantes sont incompatibles avec ce topic :}}<br><br>';
				$('.topicMismatch').each(function (_, item) {
					if (!$(item).hasClass('eqLogicAttr')) {
						var cmd = $(item).closest('tr.cmd').find('.cmdAttr[data-l1key=name]').value();
						if (cmd == '') no_name = true;
						var topic = $(item).value();
						if (cmd != '' && topic == '')
							dialog_message += '<li>{{Le topic est <b>vide</b> sur la commande}} "<b>' + cmd + '</b>"</li>';
						else if (cmd == '' && topic != '')
							dialog_message += '<li>{{Le topic}} "<b>' + topic + '</b>" {{sur une <b>commande sans nom</b>}}</li>';
						else
							dialog_message += '<li>{{Le topic}} "<b>' + topic + '</b>" {{sur la commande}} "<b>' + cmd + '</b>"</li>';
					}
				});
			}
			if (no_name) dialog_message += "<br>{{(Notez que les commandes sans nom seront supprimées lors de la sauvegarde)}}";
			dialog_message += "<br>{{Souhaitez-vous tout de même sauvegarder l'équipement ?}}";
			bootbox.confirm({
				title: "<b>{{Des problèmes ont été identifiés dans la configuration}}</b>",
				message: dialog_message,
				callback: function (result) { if (result) { // Do save
					core_save();
				}}
			});
		} else {
			core_save();
		}


	});

});
