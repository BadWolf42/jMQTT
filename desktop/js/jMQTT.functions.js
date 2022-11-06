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

// Functions used by jMQTT.js


///////////////////////////////////////////////////////////////////////////////////////////////////
// General utility functions

// Send ajax request to jMQTT plugin
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

// Get currently displayed eqId
jmqtt.getEqId = function() {
	return $('.eqLogicAttr[data-l1key=id]').value();
}

// Check if a string is a valid Json, returns json object if true, undefined otherwise
jmqtt.toJson = function(_string) {
	try {
		return JSON.parse(_string);
	} catch (e) {
		return undefined;
	}
}

// Substract properties keys present in b from a recursively (result = a - b)
jmqtt.substractKeys = function(a, b) {
	var result = {};
	for (var key in a) {
		if (typeof(a[key]) == 'object') {
			if (b[key] === undefined)
				b[key] = {};
			result[key] = jmqtt.substractKeys(a[key], b[key]);
		} else if (a[key] !== undefined && b[key] === undefined)
			result[key] = a[key];
	}
	return result;
}


///////////////////////////////////////////////////////////////////////////////////////////////////
// Used on main page

// Helper to find a logo for each Eqlogic
jmqtt.logoHelper = function(_id) {
	// Broker logo is always the same
	if (_id == 'broker')
		return 'plugins/jMQTT/core/img/node_broker.svg';

	// Search for an logo with this id
	var tmp = jmqtt.globals.logos.find(function (item) { return item.id == _id; });
	// Return path to an image according to id
	return (tmp == undefined) ? 'plugins/jMQTT/core/img/node_.svg' : ('plugins/jMQTT/core/img/' + tmp.file);
}

// Build an icon in hiddenAsTable card span
jmqtt.asCardHelper = function(_eq, _item, iClass) {
	var v    = jmqtt.globals.icons[_eq.configuration.type][_item].selector(_eq);
	var i    = jmqtt.globals.icons[_eq.configuration.type][_item][v];
	var icon = (_item == 'status') ? (i.icon + ' ' + i.color) : i.icon;
	iClass   = iClass != '' ? ' ' + iClass : '';

	return '<i class="' + icon + iClass + '"></i>';
}

// Build an icon in hiddenAsCard card span
jmqtt.asTableHelper = function(_eq, _item, aClass) {
	var v    = jmqtt.globals.icons[_eq.configuration.type][_item].selector(_eq);
	var i    = jmqtt.globals.icons[_eq.configuration.type][_item][v];
	var icon = (_eq.isEnable == '1' || _item == 'status') ? (i.icon + ' ' + i.color) : i.icon;
	var msg  = (_eq.isEnable == '1' || _item == 'status') ? (i.msg) : '';
	aClass   = aClass != '' ? ' ' + aClass : '';

	if (msg == '')
		return '<a class="btn btn-xs cursor w30' + aClass + '"><i class="' + icon + ' w18"></i></a>';
	else
		return '<a class="btn btn-xs cursor w30' + aClass + '"><i class="' + icon + ' w18 tooltips" title="' + msg + '"></i></a>';
}

// Update display card on plugin main page
jmqtt.updateDisplayCard = function (_card, _eq) {
	// Set visibility
	if (_eq.isEnable == '1')
		_card.removeClass('disableCard');
	else
		_card.addClass('disableCard');

	// Set logo
	if (_eq.configuration.type == 'broker')
		_card.find('img').attr('src', jmqtt.logoHelper('broker'));
	else
		_card.find('img').attr('src', jmqtt.logoHelper(_eq.configuration.icone));

	// Set hiddenAsTable span
	var asCard = jmqtt.asCardHelper(_eq, 'visible', 'eyed');
	if (_eq.configuration.type == 'broker') {
		asCard += jmqtt.asCardHelper(_eq, 'status', 'status-circle');
		asCard += jmqtt.asCardHelper(_eq, 'learning', 'rt-status');
	}
	_card.find('span.hiddenAsTable').empty().html(asCard);

	var asTable = '';
	asTable += jmqtt.asTableHelper(_eq, 'status', 'roundedLeft');
	asTable += jmqtt.asTableHelper(_eq, 'visible', '');
	asTable += jmqtt.asTableHelper(_eq, 'learning', '');
	asTable += jmqtt.asTableHelper(_eq, 'battery', '');
	asTable += jmqtt.asTableHelper(_eq, 'availability', '');
	asTable += '<a class="btn btn-xs cursor w30 roundedRight"><i class="fas fa-cogs eqLogicAction tooltips" title="{{Configuration avancée}}" data-action="confEq"></i></a>';
	_card.find('span.hiddenAsCard').empty().html(asTable);

	// Add Click handler on confEq
	_card.find('.eqLogicAction[data-action=confEq]').off('click').on('click', function() {
		$('#md_modal').dialog().load('index.php?v=d&modal=eqLogic.configure&eqLogic_id=' + _eq.id).dialog('open');
	});
}


///////////////////////////////////////////////////////////////////////////////////////////////////
// Used on Broker pages

// Get information about Broker state (launchable, launchable color, state, message, message color)
jmqtt.getMqttClientInfo = function(_eq) {
	// Daemon is down
	if (!jmqtt.globals.daemonState)
		return {la: 'nok', lacolor: 'danger',  state: 'nok', message: "{{Démon non démarré}}",                                      color:'danger'};
	// Client is connected to the Broker
	if (_eq.cache.mqttClientConnected)
		return {la: 'ok',  lacolor: 'success', state: 'ok',  message: "{{Le Démon jMQTT est correctement connecté à ce Broker}}",   color:'success'};
	// Client is disconnected from the Broker
	if (_eq.isEnable == '1')
		return {la: 'ok',  lacolor: 'success', state: 'pok', message: "{{Le Démon jMQTT n'arrive pas à se connecter à ce Broker}}", color:'warning'};
	// Client is disabled
	return     {la: 'nok', lacolor: 'danger',  state: 'nok', message: "{{La connexion à ce Broker est désactivée}}",                color:'danger'};
}

// On eqBroker, on Broker tab, change MQTT Client panel
jmqtt.updateBrokerTabs = function(_eq) {
	var info = jmqtt.getMqttClientInfo(_eq);

	// Update panel heading color
	$(".mqttClientPanel").removeClass('panel-success panel-warning panel-danger').addClass('panel-' + info.color);

	// Update Launchable span
	$('.mqttClientLaunchable span.label').removeClass('label-success label-warning label-danger').addClass('label-' + info.lacolor).text(info.la.toUpperCase());

	// Update Status color
	$('.mqttClientState span.label').removeClass('label-success label-warning label-danger').addClass('label-' + info.color).text(info.state.toUpperCase());
	$('.mqttClientState span.state').text(' ' + info.message);

	// Show / hide startMqttClient button
	(info.la == 'ok') ? $('.eqLogicAction[data-action=startMqttClient]').show() : $('.eqLogicAction[data-action=startMqttClient]').hide();

	// Update LastLaunch span
	$('.mqttClientLastLaunch').empty().append((_eq.cache.lastLaunchTime == undefined || _eq.cache.lastLaunchTime == '') ? '{{Inconnue}}' : _eq.cache.lastLaunchTime);

	// Set logs file
	var log = 'jMQTT_' + (_eq.name.replace(' ', '_') || 'jeedom');
	$('input[name=rd_logupdate]').attr('data-l1key', 'log::level::' + log);
	$('.eqLogicAction[data-action=modalViewLog]').attr('data-log', log);
	$('.eqLogicAction[data-action=modalViewLog]').html('<i class="fas fa-file-text-o"></i> ' + log);

	// Set logs level
	var levels = {}; levels['log::level::' + log] = _eq.configuration.loglevel; // Hack to build the array
	$('#div_broker_log').setValues(levels, '.configKey');

	// Update Real Time mode values
	$('#mqttIncTopic').value(_eq.cache.mqttIncTopic != undefined ? _eq.cache.mqttIncTopic : '#');
	$('#mqttExcTopic').value(_eq.cache.mqttExcTopic != undefined ? _eq.cache.mqttExcTopic : 'homeassistant/#');
	$('#mqttRetTopic').prop('checked', _eq.cache.mqttRetTopic != undefined ? _eq.cache.mqttRetTopic : false);

	// Update Real Time mode buttons
	jmqtt.updateRealTimeButtons(_eq.isEnable == '1', _eq.cache.realtime_mode == '1', false);
}

// Override changeObjectEqLogic function of jeedom.eqLogic.getSelectModal to take configuration (type, eqLogic) in account
jmqtt.overrideChangeObjectEqLogic = function(_eqBrokerId) {
	mod_insertEqLogic.changeObjectEqLogic = function(_select) {
		jeedom.object.getEqLogic({
			id: (_select.value() == '' ? -1 : _select.value()),
			orderByName : true,
			error: function(error) {
				$.fn.showAlert({message: error.message, level: 'danger'})
			},
			success: function(eqLogics) {
				_select.closest('tr').find('.mod_insertEqLogicValue_eqLogic').empty()
				var selectEqLogic = '<select class="form-control">'
				for (var i in eqLogics) {
					if (eqLogics[i].eqType_name                  == 'jMQTT'
							&& eqLogics[i].configuration.type    == 'eqpt'
							&& eqLogics[i].configuration.eqLogic == _eqBrokerId)
						selectEqLogic += '<option value="' + eqLogics[i].id + '">' + eqLogics[i].name + '</option>'
				}
				selectEqLogic += '</select>'
				_select.closest('tr').find('.mod_insertEqLogicValue_eqLogic').append(selectEqLogic)
			}
		})
	}
	mod_insertEqLogic.changeObjectEqLogic($('#table_mod_insertEqLogicValue_valueEqLogicToMessage td.mod_insertEqLogicValue_object select'), mod_insertEqLogic.options);
}


///////////////////////////////////////////////////////////////////////////////////////////////////
// Used on Equipment pages

// Check if a topic matches a subscription, return bool
jmqtt.checkTopicMatch = function (subscription, topic) {
	if (subscription == '') // Nothing matches an empty subscription topic
			return false;
	if (subscription == '#') // Everything matches '#' subscription topic
			return true;
	subscription = subscription.replace(/[-[\]{}()*?.,\\^$|\s]/g, '\\$&'); // Escape special character except + and #
	subscription = subscription.replaceAll('+', '[^/]*').replace('/#', '(|/.*)'); // Replace all + and 1 # by a regex
	return (new RegExp(`^${subscription}\$`)).test(topic); // Return match
}

// Action on eqLogic subscription topic field update
jmqtt.updateGlobalMainTopic = function () {
	var mt = $('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]');
	jmqtt.globals.mainTopic = mt.val();
	if (jmqtt.globals.mainTopic == '')
		mt.addClass('topicMismatch');
	else
		mt.removeClass('topicMismatch');
}

/* Rebuild the page URL from the current URL
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

/* Function to refresh the page
 * Ask confirmation if the page has been modified
 */
jmqtt.refreshEqLogicPage = function() {
	function refreshPage() {
		if (jmqtt.getEqId() != "") {
			tab = null
			if (document.location.toString().match('#')) {
				tab = '#' + document.location.toString().split('#')[1];
				if (tab != '#') {
					tab = $('a[href="' + tab + '"]')
				} else {
					tab = null
				}
			}
			$('.eqLogicDisplayCard[data-eqlogic_id="' + jmqtt.getEqId() + '"]').click();
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


///////////////////////////////////////////////////////////////////////////////////////////////////
// Used by Real Time

// Display an Alert if Real time mode has changed for this eqBroker
jmqtt.displayRealTimeEvent = function(_eq) {
	// Fetch current Real Time mode for this Broker
	var realtime = $('.eqLogicDisplayCard[jmqtt_type=broker][data-eqlogic_id=' + _eq.id + ']').find('span.hiddenAsTable i.rt-status');
	if (_eq.cache.realtime_mode == '1') {
		if (realtime.hasClass('fa-square')) {
			// Show an alert only if Real time mode was disabled
			$.fn.showAlert({message: `{{Mode Temps Réel sur le Broker <b>${_eq.name}</b> pendant 3 minutes.}}`, level: 'warning'});
		}
	} else if (realtime.hasClass('fa-sign-in-alt')) {
		// Show an alert only if Real time mode was enabled
		$.fn.showAlert({message: `{{Fin du mode Temps Réel sur le Broker <b>${_eq.name}</b>.}}`, level: 'warning'});
	}
}

// Helper to show/hide/disable Real Time buttons
jmqtt.updateRealTimeButtons = function(enabled, active, paused) {
	if (!enabled) {       // Disable buttons if eqBroker is disabled
		$('.eqLogicAction[data-action=startRealTimeMode]').show().addClass('disabled');
		$('.eqLogicAction[data-action=stopRealTimeMode]').hide();
		$('.eqLogicAction[data-action=playRealTime]').hide();
		$('.eqLogicAction[data-action=pauseRealTime]').hide();
		$('#mqttIncTopic').attr('disabled', '');
		$('#mqttExcTopic').attr('disabled', '');
		$('#mqttRetTopic').attr('disabled', '');
		clearInterval(jmqtt.globals.refreshRealTime);
	} else if (!active) { // Show only startRealTimeMode button
		$('.eqLogicAction[data-action=startRealTimeMode]').show().removeClass('disabled');
		$('.eqLogicAction[data-action=stopRealTimeMode]').hide();
		$('.eqLogicAction[data-action=playRealTime]').hide();
		$('.eqLogicAction[data-action=pauseRealTime]').hide();
		$('#mqttIncTopic').removeAttr('disabled');
		$('#mqttExcTopic').removeAttr('disabled');
		$('#mqttRetTopic').removeAttr('disabled');
		clearInterval(jmqtt.globals.refreshRealTime);
	} else if (paused) { // Show only stopRealTimeMode & playRealTimeMode button
		$('.eqLogicAction[data-action=startRealTimeMode]').hide();
		$('.eqLogicAction[data-action=stopRealTimeMode]').show();
		$('.eqLogicAction[data-action=playRealTime]').show();
		$('.eqLogicAction[data-action=pauseRealTime]').hide();
		$('#mqttIncTopic').attr('disabled', '');
		$('#mqttExcTopic').attr('disabled', '');
		$('#mqttRetTopic').attr('disabled', '');
		clearInterval(jmqtt.globals.refreshRealTime);
	} else {              // Show stopRealTimeMode & pauseRealTimeMode button
		$('.eqLogicAction[data-action=startRealTimeMode]').hide();
		$('.eqLogicAction[data-action=stopRealTimeMode]').show();
		$('.eqLogicAction[data-action=playRealTime]').hide();
		$('.eqLogicAction[data-action=pauseRealTime]').show();
		$('#mqttIncTopic').attr('disabled', '');
		$('#mqttExcTopic').attr('disabled', '');
		$('#mqttRetTopic').attr('disabled', '');
		// Load Real Time Data every 3s if stopRealTimeMode button is visible
		jmqtt.globals.refreshRealTime = setInterval(function() {
				if ($('.eqLogicAction[data-action=stopRealTimeMode]:visible').length == 0)
					return;
				jmqtt.getRealTimeData();
			}, 2000);
	}
}

// Inform Jeedom to change Real Time mode
jmqtt.changeRealTimeMode = function(_id, _mode) {
	// Ajax call to inform the plugin core of the change
	jmqtt.callPluginAjax({
		data: {
			action: "changeRealTimeMode",
			mode: _mode,
			id: _id,
			subscribe: $('#mqttIncTopic').val(),
			exclude: $('#mqttExcTopic').val(),
			retained: $('#mqttRetTopic').is(':checked')
		}
	});
}

// Build a new line in Real Time tab of a eqBroker
jmqtt.newRealTimeCmd = function(_data) {
	var tr = '<tr class="rtCmd" data-brkId="' + _data.id + '"' + ((_data.id == jmqtt.getEqId()) ? '' : ' style="display: none;"') + '>';
	tr += '<td class="fitwidth"><span class="cmdAttr">' + (_data.date ? _data.date : '') + '</span></td>';
	tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="topic" style="margin-bottom:5px;" value="' + _data.topic + '" disabled>';
	tr += '<input class="cmdAttr form-control input-sm col-lg-11 col-md-10 col-sm-10 col-xs-10" style="float: right;" data-l1key="jsonPath" value="' + _data.jsonPath + '" disabled></td>';
	tr += '<td><textarea class="cmdAttr form-control input-sm" data-l1key="payload" style="min-height:65px;" readonly=true disabled>' + _data.payload + '</textarea></td>';
	tr += '<td align="center"><input class="cmdAttr form-control" data-l1key="qos" style="display:none;" value="' + _data.qos + '" disabled>';
	if (_data.retain)
		tr += '<i class="fas fa-database warning tooltips" title="{{Ce message est stocké sur le Broker (Retain)}}"></i>';
	if (_data.retain && _data.existing)
		tr += '<br /><br />';
	if (_data.existing)
		tr += '<i class="fas fa-sign-in-alt fa-rotate-90 success tooltips" title="{{Ce topic est compatible avec le(s) équipement(s) :}}' + _data.existing + '"></i>';
	tr += '</td><td align="right"><div class="input-group pull-right" style="display:inline-flex">';
	// tr += '<a class="btn btn-primary btn-sm roundedLeft cmdAction tooltips" data-action="addEq" title="{{TODO (nice to have)<br/>Ajouter un nouvel équipement souscrivant à ce topic}}"><i class="fas fa-plus-circle"></i></a>';
	tr += '<a class="btn btn-success btn-sm roundedLeft cmdAction tooltips" data-action="addCmd" title="{{Ajouter à un équipement existant}}"><i class="far fa-plus-square"></i></a>';
	if (typeof(jmqtt.toJson(_data.payload)) === 'object')
		tr += '<a class="btn btn-warning btn-sm cmdAction tooltips" title="{{Découper ce json en commandes}}" data-action="splitJson"><i class="fas fa-expand-alt"></i></a>';
	else
		tr += '<a class="btn btn-default disabled btn-sm cmdAction" data-action="splitJson"><i class="fas fa-expand-alt"></i></a>';
	tr += '<a class="btn btn-danger btn-sm roundedRight cmdAction tooltips" data-action="remove" title="{{Supprimer de la vue}}"><i class="fas fa-minus-circle"></i></a></div></td>';
	tr += '</tr>';
	return tr;
}

// Get new Real Time data from Daemon
jmqtt.getRealTimeData = function() {
	// Avoid simultaneous collection
	if (jmqtt.globals.lockRealTime)
		return;
	jmqtt.globals.lockRealTime = true;
	var _since = $('#table_realtime').attr('since');
	_since = ((_since == undefined) ? '' : _since);
	jmqtt.callPluginAjax({
		data: {
			action: "realTimeGet",
			id: jmqtt.getEqId(),
			since: _since
		},
		error: function (error) {
			$.fn.showAlert({message: error.message, level: 'danger'});
			jmqtt.globals.lockRealTime = false;
		},
		success: function (data) {
			if (data.length > 0) {
				var realtime = $('#table_realtime tbody');
				for (var i in data) {
					realtime.prepend(jmqtt.newRealTimeCmd(data[i]));
					_since = data[i].date;
				}
				$('#table_realtime').attr('since', _since);
				$('#table_realtime').trigger("update");
			}
			jmqtt.globals.lockRealTime = false;
		}
	});
}
