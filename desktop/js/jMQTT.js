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

// Missing stopPropagation for textarea in command list
// cf PR to Jeedom Core : https://github.com/jeedom/core/pull/1821
// Will be removed after PR integrated to Jeedom release
$('#div_pageContainer').on('dblclick', '.cmd textarea', function(event) {
	event.stopPropagation()
});

//To memorise page refresh timeout when set
var refreshTimeout;

function callPluginAjax(_params) {
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

// Rebuild the page URL from the current URL
//
// filter: array of parameters to be removed from the URL
// id:     if not empty, it is appended to the URL (in that case, 'id' should be passed within the filter.
// hash:   if provided, it is appended at the end of the URL (shall contain the # character). If a hash was already
//         present, it is replaced by that one.
function initPluginUrl(filter=['id', 'saveSuccessFull','removeSuccessFull', 'hash'], id='', hash='') {
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

// Function to refresh the page
// Ask confirmation if the page has been modified
function refreshEqLogicPage() {
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
	//console.log('refreshEqLogicPage: ' + $('.eqLogicAttr[data-l1key=id]').value());
	if (modifyWithoutSave) {
		bootbox.confirm("{{La page a été modifiée. Etes-vous sûr de vouloir la recharger sans sauver ?}}", function (result) {
			if (result)
				refreshPage();
		});
	}
	else
		refreshPage();
}

$(document).ready(function() {
	// On page load, show the commandtab menu bar if necessary (fix #64)
	if (document.location.hash == '#commandtab' && $('.eqLogicAttr[data-l1key="configuration"][data-l2key="type"]').value() != 'broker') {
		$('#menu-bar').show();
	}

	// Done here, otherwise the refresh button remains selected
	$('.eqLogicAction[data-action=refreshPage]').removeAttr('href').off('click').on('click', function(event) {
		event.stopPropagation();
		refreshEqLogicPage();
	});

	// Add/remove special char before JSON starting by '{' because Jeedom Core breaks integer, boolean and null values
	// TODO : To Be delete when fix reached Jeedom Core stable
	// https://github.com/jeedom/core/pull/1825
	// https://github.com/jeedom/core/pull/1829
	$('.eqLogicAction[data-action=save]').mousedown(function() {
		requestTextareas = $('.cmdAttr[data-l1key=configuration][data-l2key=request]')
		requestTextareas.each(function(i, e){
			currentValue = $(e).val();
			if (currentValue.length >= 1) {
				if (currentValue[0] == '{') $(e).val(String.fromCharCode(6) + currentValue);
				else if (currentValue.length == String.fromCharCode(6)) $(e).val('');
				else if (currentValue.length >= 2 && currentValue[0] == String.fromCharCode(6) && currentValue[1] != '{') $(e).val(currentValue.substring(1));
			}
		});
	});
});

$("#bt_addMQTTInfo").on('click', function(event) {
	var _cmd = {type: 'info'};
	addCmdToTable(_cmd);
	modifyWithoutSave = true;
});

$("#bt_addMQTTAction").on('click', function(event) {
	var _cmd = {type: 'action'};
	addCmdToTable(_cmd);
	modifyWithoutSave = true;
});

$('.eqLogicAction[data-action=healthMQTT]').on('click', function () {
	$('#md_modal').dialog({title: "{{Santé jMQTT}}"});
	$('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=health').dialog('open');
});

$('.eqLogicAction[data-action=templatesMQTT]').on('click', function () {
	$('#md_modal').dialog({title: "{{Gestion des templates d'équipements}}"});
	$('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=templates').dialog('open');
});

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

$('#bt_classic').on('click', function() {
	refreshEqLogicPage();
	$('#bt_classic').removeClass('btn-default').addClass('btn-primary');
	$('#bt_json').removeClass('btn-primary').addClass('btn-default');
});

$('#bt_json').on('click', function() {
	refreshEqLogicPage();
	$('#bt_json').removeClass('btn-default').addClass('btn-primary');
	$('#bt_classic').removeClass('btn-primary').addClass('btn-default');
});

$('.nav-tabs a[href="#eqlogictab"],.nav-tabs a[href="#brokertab"]').on('click', function() {
	$('#menu-bar').hide();
});

$('.nav-tabs a[href="#commandtab"]').on('click', function() {
	if($('.eqLogicAttr[data-l1key="configuration"][data-l2key="type"]').value() != 'broker') {
		$('#menu-bar').show();
	}
});

$('.eqLogicAttr[data-l1key="configuration"][data-l2key="type"]').on('change', function(e) {
	if($(e.target).value() == 'broker') {
		$('#menu-bar').hide();
	}
});

// Configure the sortable functionality of the commands array
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

// Restrict "cmd.configure" modal popup when double-click on command without id
$('#table_cmd').on('dblclick', '.cmd[data-cmd_id=""]', function(event) {
	event.stopPropagation()
});

/**
 * Add jMQTT equipment callback
 */
$('.eqLogicAction[data-action=add_jmqtt]').on('click', function () {
	if (typeof $(this).attr('brkId') === 'undefined') {
		var eqL = {type: 'broker', brkId: -1};
		var prompt = "{{Nom du broker ?}}";
	}
	else {
		var eqL = {type: 'eqpt', brkId: $(this).attr('brkId')};
		var prompt = "{{Nom de l'équipement ?}}";
	}
	bootbox.prompt(prompt, function (result) {
		if (result !== null) {
			jeedom.eqLogic.save({
				type: eqType,
				eqLogics: [ $.extend({name: result}, eqL) ],
				error: function (error) {
					$('#div_alert').showAlert({message: error.message, level: 'danger'});
				},
				success: function (data) {
					var url = initPluginUrl();
					modifyWithoutSave = false;
					url += '&id=' + data.id + '&saveSuccessFull=1';
					loadPage(url);
				}
			});
		}
	});
});

$('.eqLogicAction[data-action=remove_jmqtt]').on('click', function () {
	function remove_jmqtt() {
		jeedom.eqLogic.remove({
			type: eqType,
			id: $('.eqLogicAttr[data-l1key=id]').value(),
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

	if ($('.eqLogicAttr[data-l1key=id]').value() != undefined) {
		var typ = $('.eqLogicAttr[data-l2key=type]').value() == 'broker' ? 'broker' : 'module';
		bootbox.confirm('{{Etes-vous sûr de vouloir supprimer}}' + ' ' +
				(typ == 'broker' ? '{{le broker}}' : "{{l'équipement}}") + ' <b>' + $('.eqLogicAttr[data-l1key=name]').value() + '</b> ?', function (result) {
			if (result) {
				if (typ == 'broker') {
					bootbox.confirm('<table><tr><td style="vertical-align:middle;font-size:2em;padding-right:10px"><span class="label label-warning"><i class="fa fa-warning"</i>' +
						'</span></td><td style="vertical-align:middle">' + '{{Tous les équipements associés au broker vont être supprimés}}' +
						'...<br><b>' + '{{Êtes vous sûr ?}}' + '</b></td></tr></table>', function (result) {
						if (result) {
							remove_jmqtt();
						}
					});
				}
				else {
					remove_jmqtt();
				}
			}
		});
	} else {
		$('#div_alert').showAlert({message: '{{Veuillez d\'abord sélectionner un}} ' + eqType, level: 'danger'});
	}
});

$('#mqtttopic').on('dblclick', function() {
	if($(this).val() == "") {
		var brokername = $('#broker option:selected').text();
		var eqName = $('.eqLogicAttr[data-l1key=name]').value();
		$(this).val(brokername+'/'+eqName+'/#');
	}
});

$('.eqLogicAction[data-action=move_broker]').on('click', function () {
	var id = $('.eqLogicAttr[data-l1key=id]').value();
	var brk_id = $('#broker').val();
	if (id != undefined && brk_id != undefined) {
		bootbox.confirm('<table><tr><td style="vertical-align:middle;font-size:2em;padding-right:10px"><span class="label label-warning"><i class="fa fa-warning"</i>' +
			'</span></td><td style="vertical-align:middle">' + "{{Vous êtes sur le point de changer l'équipement de broker}}" +
			'.<br>' + '{{Êtes vous sûr ?}}' + '</td></tr></table>', function (result) {
			if (result) {
				callPluginAjax({
					async: false,
					data: {
						action: 'moveToBroker',
						id: id,
						brk_id: brk_id
					},
					success: function (data) {
						window.location.reload();
					}
				});
			}
		});
	}
});

$('.applyTemplate').off('click').on('click', function () {
	callPluginAjax({
		data: {
			action: "getTemplateList",
		},
		success: function (dataresult) {
			var dialog_message = '<label class="control-label">{{Choisissez un template : }}</label> ';
			dialog_message += '<select class="bootbox-input bootbox-input-select form-control" id="applyTemplateSelector">';
			for(var i in dataresult){ dialog_message += '<option value="'+dataresult[i][0]+'">'+dataresult[i][0]+'</option>'; }
			dialog_message += '</select><br>';

			dialog_message += '<label class="control-label">{{Saisissez le Topic de base : }}</label> ';
			var currentTopic = $('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').value();
			if (currentTopic.endsWith("#") || currentTopic.endsWith("+")) {currentTopic = currentTopic.substr(0,currentTopic.length-1);}
			if (currentTopic.endsWith("/")) {currentTopic = currentTopic.substr(0,currentTopic.length-1);}
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
					callPluginAjax({
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

$('.createTemplate').off('click').on('click', function () {
	bootbox.prompt({
		title: "Nom du nouveau template ?",
		callback: function (result) {
			if (result !== null) {
				callPluginAjax({
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

/**
 * printEqLogic callback called by plugin.template before calling addCmdToTable.
 *   . Reorder commands if the JSON view is active
 *   . Show the fields depending on the type (broker or equipment)
 */
function printEqLogic(_eqLogic) {

	// Initialize the counter of the next root level command to be added
	var n_cmd = 1;

	// Is the JSON view is active
	var is_json_view = $('#bt_json.active').length != 0;

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
		for (var c of _eqLogic.cmd) {
			c.tree_id = (n_cmd++).toString();
		}

		// Classical view: enable the sortable functionality
		$("#table_cmd").sortable('enable');
	}

	// Show UI elements depending on the type
	if ((_eqLogic.configuration.type == 'eqpt' && (_eqLogic.configuration.brkId == undefined || _eqLogic.configuration.brkId < 0)) ||
			(_eqLogic.configuration.type != 'eqpt' && _eqLogic.configuration.type != 'broker')) {
		$('.toDisable').addClass('disabled');
		$('.eqLogicAction[data-action="configure"]').removeClass('roundedLeft');
		$('.typ-brk').hide();
		$('.typ-std').show();
	}
	else if (_eqLogic.configuration.type == 'broker') {
		$('.toDisable').removeClass('disabled');
		$('.eqLogicAction[data-action="configure"]').addClass('roundedLeft');
		$('.typ-std').hide();
		$('.typ-brk').show();
		$('#mqtttopic').prop('readonly', true);
		var log = 'jMQTT_' + (_eqLogic.name.replace(' ', '_') || 'jeedom');
		$('input[name=rd_logupdate]').attr('data-l1key', 'log::level::' + log);
		$('.bt_plugin_conf_view_log').attr('data-log', log);
		$('.bt_plugin_conf_view_log').html('<i class="fa fa fa-file-text-o"></i> ' + log);

		refreshMqttClientInfo();

		jeedom.config.load({
			configuration: $('#div_broker_log').getValues('.configKey')[0],
			plugin: 'jMQTT',
			error: function (error) {
				$('#div_alert').showAlert({message: error.message, level: 'danger'});
			},
			success: function (data) {
				$('#div_broker_log').setValues(data, '.configKey');
			}
		});
	}
	else if (_eqLogic.configuration.type == 'eqpt') {
		$('.toDisable').removeClass('disabled');
		$('.eqLogicAction[data-action="configure"]').removeClass('roundedLeft');
		$('.typ-brk').hide();
		$('.typ-std').show();
		$('#mqtttopic').prop('readonly', false);
	}

	// Initialise the broker dropbox
	var brokers = $("#broker");
	brokers.empty();
	$.each( eqBrokers, function(key, name) {
		brokers.append(new Option(name, key));
	});
	brokers.val(_eqLogic.configuration.brkId);
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
		// get hiden settings for Broker and remove them of eqLogic
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

	if (!isset(_cmd)) {
		var _cmd = {configuration: {}};
	}
	if (!isset(_cmd.configuration)) {
		_cmd.configuration = {};
	}

	// Is the JSON view is active
	var is_json_view = $('#bt_json.active').length != 0;

	if (!isset(_cmd.tree_id)) {
		//looking for all tree-id, keep part before the first dot, convert to Int
		var root_tree_ids = $('[tree-id]').map((pos,e) => parseInt(e.getAttribute("tree-id").split('.')[0]))

		//if some tree-id has been found
		if (root_tree_ids.length > 0) {
			_cmd.tree_id = (Math.max.apply(null, root_tree_ids) + 1).toString(); //use the highest one plus one
		}
		else {
			_cmd.tree_id = '1'; // else this is the first one
		}
	}

	if (init(_cmd.type) == 'info') {
		// FIXME: is this disabled variable usefull?
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
		if (is_numeric(_cmd.id)) {
			tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span><br> ';
			tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span><br> ';
			tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span><br> ';
			tr += '</td><td>';
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> ';
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>';
		}
		else {
			tr += '</td><td>';
		}
		if (!is_json_view && _cmd.configuration.irremovable == undefined) {
			tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
		}
		tr += '</td></tr>';

		$('#table_cmd tbody').append(tr);
		$('#table_cmd [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
		if (isset(_cmd.type)) {
			$('#table_cmd [tree-id="' + _cmd.tree_id + '"] .cmdAttr[data-l1key=type]').value(init(_cmd.type));
		}
		jeedom.cmd.changeType($('#table_cmd [tree-id="' + _cmd.tree_id + '"]'), init(_cmd.subType));

		// Fill in value of current cmd. Efficient in JSON view only as _cmd.value was set in JSON view only in printEqLogic.
		if (is_json_view) {
			$('#table_cmd [tree-id="' + _cmd.tree_id + '"] .form-control[data-key=value]').value(_cmd.value);
		}

		// refreshValue apply value on cmd with id (so there won't be any refresh on cmd without id in JSON view)
		function refreshValue(val) {
			$('#table_cmd [tree-id="' + _cmd.tree_id + '"][data-cmd_id="' + _cmd.id + '"] .form-control[data-key=value]').value(val);
		}

		if (_cmd.id != undefined) {
			// Get and display the value in CLASSIC view (for JSON view, see few lines above)
			if (! is_json_view) {
				jeedom.cmd.execute({
					id: _cmd.id,
					cache: 0,
					notify: false,
					success: function(result) {
						refreshValue(result);
				}});
			}

			// Set the update value callback
			jeedom.cmd.update[_cmd.id] = function(_options) {
				refreshValue(_options.display_value);
			}
		}

		$('#table_cmd [tree-id="' + _cmd.tree_id + '"]').show(); // SPEED Improvement : Create TR hiden then show it at the end after setValues, etc.
	}

	if (init(_cmd.type) == 'action') {
		// FIXME: is this disabled variable usefull? Re-added to avoid "undefined"
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
//		tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" placeholder="{{Topic}}" style="margin-bottom: 32px;" ' + disabled + '>';
		tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" style="min-height:62px;margin-top:14px;"' + disabled + ' placeholder="{{Topic}}"></textarea><br/>';
		tr += '</td><td>';
//		tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" placeholder="{{Valeur}}" ' + disabled + '>';
		tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="height:18px;" ' + disabled + ' placeholder="{{Valeur}}"></textarea>';
		tr += '<a class="btn btn-default btn-sm cursor listEquipementInfo" data-input="request" style="margin-top:5px;"><i class="fa fa-list-alt "></i> {{Rechercher équipement}}</a>';
		tr +='</select></span>';
		tr += '</td><td>';
		tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:50px;display:inline-block;">';
		tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:50px;display:inline-block;">';
		tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste de valeur|texte séparé par ;}}" title="{{Liste}}">';
		tr += '</td><td>';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span><br> ';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="retain"/>{{Retain}}</label></span><br> ';
		tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="autoPub"/>{{Pub. auto}} <sup><i class="fa fa-question-circle tooltips" title="Publication automatique en MQTT lors d\'un changement <br>(Utiliser avec au moins une commande info dans Valeur)."></i></sup></label></span><br> ';
		tr += '<span class="checkbox-inline">{{Qos}}: <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="Qos" placeholder="{{Qos}}" title="{{Qos}}" style="width:50px;display:inline-block;"></span> ';
		tr += '</td>';
		tr += '<td>';
		if (is_numeric(_cmd.id)) {
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fa fa-cogs"></i></a> ';
			tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
		}
		if (!is_json_view && _cmd.configuration.irremovable == undefined) {
			tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>'
		}
		tr += '</td></tr>';

		$('#table_cmd tbody').append(tr);
		// $('#table_cmd [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
		var tr = $('#table_cmd [tree-id="' + _cmd.tree_id + '"]');
		jeedom.eqLogic.builSelectCmd({
			id: $('.eqLogicAttr[data-l1key=id]').value(),
			filter: {type: 'info'},
			error: function (error) {
				$('#div_alert').showAlert({message: error.message, level: 'danger'});
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

/**
 * Management of cmdTopicMismatch event sent by the plugin core
 * @param _event string event name
 * @param _options['eqlogic_name'] string name of the eqLogic command is added to
 * @param _options['cmd_name'] string name of the new command
 */
$('body').off('jMQTT::cmdTopicMismatch').on('jMQTT::cmdTopicMismatch', function(_event,_options) {
	if ($('#div_cmdMsg').is(':empty') || $('#div_cmdMsg').is(':hidden'))
		var msg = '{{La commande}} <b>' + _options['cmd_name'] + "</b> {{a un topic incompatible du topic d'inscription de l\'équipement}}" +
		' <b>' + _options['eqlogic_name'] + '</b>.';
	else
		var msg = "{{Plusieurs commandes ont des topics incompatibles du topic d'inscription de l\'équipement}} <b>" + _options['eqlogic_name'] + '</b>.';

	$('#div_cmdMsg').showAlert({message: msg, level: 'warning'});
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
$('body').off('jMQTT::cmdAdded').on('jMQTT::cmdAdded', function(_event,_options) {
	if ($('#div_cmdMsg').is(':empty') || $('#div_cmdMsg').is(':hidden'))
		var msg = '{{La commande}} <b>' + _options['cmd_name'] + '</b> {{est ajoutée à l\'équipement}}' +
		' <b>' + _options['eqlogic_name'] + '</b>.';
	else
		var msg = '{{Plusieurs commandes sont ajoutées à l\'équipement}} <b>' + _options['eqlogic_name'] + '</b>.';

	// If the page is being modified or another equipment is being consulted or a dialog box is shown: display a simple alert message
	if (modifyWithoutSave || ( $('.eqLogic').is(":visible") && $('.eqLogicAttr[data-l1key=id]').value() != _options['eqlogic_id'] ) ||
			$('div[role="dialog"]').filter(':visible').length != 0 || !_options['reload']) {
		$('#div_cmdMsg').showAlert({message: msg, level: 'warning'});
	}
	// Otherwise: display an alert message and reload the page
	else {
		$('#div_cmdMsg').showAlert({
			message: msg + ' {{La page va se réactualiser automatiquement}}.',
			level: 'warning'
		});
		// Reload the page after a delay to let the user read the message
		if (refreshTimeout === undefined) {
			refreshTimeout = setTimeout(function() {
				refreshTimeout = undefined;
				$('.eqLogicAction[data-action=refreshPage]').click();
			}, 3000);
		}
	}
});

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//
// Management of the include button and mode
//

// Configure the display according to the given mode
//If given mode is not provided, use the bt_changeIncludeMode data-mode attribute value
function configureIncludeModeDisplay(brkId, mode) {
	if (mode == 1) {
		//$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']:not(.card)').removeClass('btn-default').addClass('btn-success');
		$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').attr('data-mode', 1);
		$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+'].card span').text('{{Arrêter l\'inclusion}}');
		$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').addClass('include');
		$('#div_inclusionModeMsg').showAlert({message: '{{Mode inclusion automatique pendant 2 à 3min. Cliquez sur le bouton pour forcer la sortie de ce mode avant.}}', level: 'warning'});
	} else {
		//$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']:not(.card)').addClass('btn-default').removeClass('btn-success btn-danger');
		$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').attr('data-mode', 0);
		$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+'].card span').text('{{Mode inclusion}}');
		$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').removeClass('include');
		$('#div_inclusionModeMsg').hideAlert();
	}
}

function setIncludeModeActivation(brkId, broker_state) {
	if (broker_state == "ok") {
		$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').removeClass('disableCard').on('click', changeIncludeMode);
	}
	else {
		$('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').addClass('disableCard').unbind();;
	}
}

function changeIncludeMode() {
	var el = $(this);

	// Invert the button display and show the alert message
	if (el.attr('data-mode') == 1) {
		configureIncludeModeDisplay(el.attr('brkId'),0);
	}
	else {
		configureIncludeModeDisplay(el.attr('brkId'),1);
	}

	// Ajax call to inform the plugin core of the change
	callPluginAjax({
		data: {
			action: "changeIncludeMode",
			mode: el.attr('data-mode'),
			id: el.attr('brkId')
		}
	});
}

// Update the broker icon and the include mode activation on reception of a new state event
$('body').off('jMQTT::EventState').on('jMQTT::EventState', function (_event,_options) {
	showMqttClientInfo(_options);
	setIncludeModeActivation(_options.brkId, _options.state);
	if (_options.launchable == 'ok')
		$('.eqLogicDisplayCard[jmqtt_type="broker"][data-eqlogic_id="' + _options.brkId + '"]').removeClass('disableCard')
	else
		$('.eqLogicDisplayCard[jmqtt_type="broker"][data-eqlogic_id="' + _options.brkId + '"]').addClass('disableCard')
	$('.eqLogicDisplayCard[jmqtt_type="broker"][data-eqlogic_id="' + _options.brkId + '"] .status-circle').removeClass('fa-check-circle').removeClass('fa-minus-circle').removeClass('fa-times-circle').addClass(_options.icon);
	$('.eqLogicDisplayCard[jmqtt_type="broker"][data-eqlogic_id="' + _options.brkId + '"] .status-circle').css('color', _options.color);
});

//Called by the plugin core to inform about the automatic inclusion mode disabling
$('body').off('jMQTT::disableIncludeMode').on('jMQTT::disableIncludeMode', function (_event,_options) {
	// Change display accordingly
	configureIncludeModeDisplay(_options['brkId'], 0);
});

/**
 * Called by the plugin core to inform about the inclusion of an equipment
 *
 * @param {string} _event event name (jMQTT::eqptAdded in this context)
 * @param {string} _options['eqlogic_name'] string name of the eqLogic command is added to
 */
$('body').off('jMQTT::eqptAdded').on('jMQTT::eqptAdded', function (_event,_options) {

	var msg = '{{L\'équipement}} <b>' + _options['eqlogic_name'] + '</b> {{vient d\'être inclu}}';

	// If the page is being modified or an equipment is being consulted or a dialog box is shown: display a simple alert message
	// Otherwise: display an alert message and reload the page
	if (modifyWithoutSave || $('.eqLogic').is(":visible") || $('div[role="dialog"]').filter(':visible').length != 0) {
		$('#div_newEqptMsg').showAlert({message: msg + '.', level: 'warning'});
	}
	else {
		$('#div_newEqptMsg').showAlert({
			message: msg + '. {{La page va se réactualiser automatiquement}}.',
			level: 'warning'
		});
		// Reload the page after a delay to let the user read the message
		if (refreshTimeout === undefined) {
			refreshTimeout = setTimeout(function() {
				refreshTimeout = undefined;
				window.location.reload();
			}, 3000);
		}
	}
});

