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

// Namespace
jmqtt = {}

// Functions used by jMQTT.js

///////////////////////////////////////////////////////////////////////////////////////////////////
// Backward compatibility functions

// TODO: Remove core4.2 backward compatibility `PageModified` js function
//  Remove when Jeedom 4.2 is no longer supported
//  `jmqtt.isPageModified` -> `jeeFrontEnd.modifyWithoutSave`
//  `jmqtt.setPageModified` -> `jeeFrontEnd.modifyWithoutSave = true`
//  `jmqtt.unsetPageModified` -> `jeeFrontEnd.modifyWithoutSave = false`
//  labels: workarround, core4.2, javascript

// Handle get modifyWithoutSave flag in jeeFrontEnd or window
jmqtt.isPageModified = function() {
    return jeeFrontEnd.modifyWithoutSave || window.modifyWithoutSave;
}

// Handle set modifyWithoutSave flag in jeeFrontEnd and window
jmqtt.setPageModified = function() {
    jeeFrontEnd.modifyWithoutSave = true;
    window.modifyWithoutSave = true;
}

// Handle clear modifyWithoutSave flag in jeeFrontEnd and window
jmqtt.unsetPageModified = function() {
    jeeFrontEnd.modifyWithoutSave = false;
    window.modifyWithoutSave = false;
}

// TODO: Remove core4.3 backward compatibility `CmdsSortable` js function
//  Remove when Jeedom 4.3 is no longer supported
//  `jmqtt.setCmdsSortable(true)` -> `jeeFrontEnd.pluginTemplate.cmdSortable.options.disabled = false`
//  `jmqtt.setCmdsSortable(false)` -> `jeeFrontEnd.pluginTemplate.cmdSortable.options.disabled = true`
//  labels: workarround, core4.3, javascript

// Handle sortability of table "table_cmd"
jmqtt.setCmdsSortable = function(_status) {
    if ($('#table_cmd').sortable('instance')) {
        $('#table_cmd').sortable(_status ? 'enable' : 'disable');
    } else if (document.getElementById('table_cmd')._sortable) {
        jeeFrontEnd.pluginTemplate.cmdSortable.options.disabled = (_status ? false : true);
    }
}


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

// Get broker Id of the currently displayed eq
jmqtt.getBrkId = function() {
    if ($('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val() == 'broker')
        return $('.eqLogicAttr[data-l1key=id]').value();
    else
        return $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqLogic]').value();
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
            result[key] = (key == 'cmd') ? a[key] : jmqtt.substractKeys(a[key], b[key]);
        } else if (a[key] !== undefined && b[key] === undefined)
            result[key] = a[key];
    }
    return result;
}


///////////////////////////////////////////////////////////////////////////////////////////////////
// Used on main page

// Build an icon in hiddenAsTable card span
jmqtt.asCardHelper = function(_eq, _item, iClass) {
    iClass   = iClass != '' ? ' ' + iClass : '';

    // Handle bad eqLogics (orphans)
    if (_eq.configuration == undefined || _eq.configuration.type == undefined || jmqtt_globals.icons[_eq.configuration.type][_item] == undefined)
        return '<i class="' + iClass + '"></i>';

    var v    = jmqtt_globals.icons[_eq.configuration.type][_item].selector(_eq);
    var i    = jmqtt_globals.icons[_eq.configuration.type][_item][v];
    var icon = (_item == 'status') ? (i.icon + ' ' + i.color) : i.icon;

    return '<i class="' + icon + iClass + '"></i>';
}

// Build an icon in hiddenAsCard card span
jmqtt.asTableHelper = function(_eq, _item, aClass) {
    aClass = aClass != '' ? ' ' + aClass : '';

    // Handle bad eqLogics (orphans)
    if (_eq.configuration == undefined || _eq.configuration.type == undefined || jmqtt_globals.icons[_eq.configuration.type][_item] == undefined)
        return '';

    var v    = jmqtt_globals.icons[_eq.configuration.type][_item].selector(_eq);
    var i    = jmqtt_globals.icons[_eq.configuration.type][_item][v];
    // var colored = _eq.configuration.type == 'broker' && (jmqtt_globals.daemonState && _eq.isEnable == '1') && _item == 'status';
    var colored = _item == 'status' || (_eq.isEnable == '1' && (_eq.configuration.type != 'broker' || jmqtt_globals.daemonState));
    //var icon = colored ? (i.icon + ' ' + i.color) : i.icon;
    var msg  = colored ? (i.msg) : '';

    if (msg == '')
        return '';
    else
        return '<span class="' + (i.color == '' ? 'label label-info' : ('label label-' + i.color)) + '">' + msg + '</span>&nbsp;';
}

// Update display card on plugin main page
jmqtt.updateDisplayCard = function (_card, _eq) {
    // Set visibility
    if (_eq.isEnable == '1')
        _card.removeClass('disableCard');
    else
        _card.addClass('disableCard');

    // Set logo
    if (_eq.configuration.type == 'broker') {
        var logo = 'plugins/jMQTT/core/img/node_broker.svg';
    } else if (_eq.configuration.icone != undefined) {
        var logo = 'plugins/jMQTT/core/img/node_' + _eq.configuration.icone + '.svg';
    } else {
        var logo = 'plugins/jMQTT/core/img/node_.svg';
    }
    if (_card.find('img').attr('src') != logo) {
        _card.find('img').attr('src', logo);
    }

    // Set hiddenAsTable span
    var asCard = jmqtt.asCardHelper(_eq, 'visible', 'eyed');
    if (_eq.configuration.type == 'broker') {
        asCard += jmqtt.asCardHelper(_eq, 'status', 'status-circle');
        asCard += jmqtt.asCardHelper(_eq, 'learning', 'rt-status');
        // Store Real Time parameters in eqLogicDisplayCard
        _card[0].dataset.rtRun = _eq.cache.realtime_mode == '1' ? 1 : 0;
        _card[0].dataset.rtInc = _eq.cache.mqttIncTopic != undefined ? _eq.cache.mqttIncTopic : '#';
        _card[0].dataset.rtExc = _eq.cache.mqttExcTopic != undefined ? _eq.cache.mqttExcTopic : 'homeassistant/#';
        _card[0].dataset.rtRet = _eq.cache.mqttRetTopic != undefined ? _eq.cache.mqttRetTopic : '0';
        _card[0].dataset.rtDur = _eq.cache.mqttDuration != undefined ? _eq.cache.mqttDuration : '180';
    }
    _card.find('span.hiddenAsTable').empty().html(asCard);

    var asTable = '';
    asTable += jmqtt.asTableHelper(_eq, 'status', '');
    asTable += jmqtt.asTableHelper(_eq, 'visible', '');
    asTable += jmqtt.asTableHelper(_eq, 'learning', '');
    asTable += jmqtt.asTableHelper(_eq, 'battery', '');
    asTable += jmqtt.asTableHelper(_eq, 'availability', '');
    asTable += '<span><i class="fas fa-cogs eqLogicAction tooltips" title="{{Configuration avancée}}" data-action="confEq"></i></span>';
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
    if (!jmqtt_globals.daemonState)
        return {la: 'nok', lacolor: 'danger',  state: 'nok', message: "{{Le Démon n'est pas démarré}}",                             color:'danger'};
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

    // Update Real Time tab
    jmqtt.updateRealTimeTab(_eq.id, false);
}

// On drag of a certificate file in a Broker tab
jmqtt.certDrag = function(ev) {
    ev.preventDefault();
    ev.stopPropagation();
    if (ev.type == 'dragenter') {
        jmqtt_globals.dropzoneCpt++;
        $('.dropzone').show().css('background-color', '');
        if ($(ev.target).hasClass('dropzone'))
            $(ev.target).css('background-color', 'lightyellow');
    } else if (ev.type == 'dragleave') {
        if ($(ev.target).hasClass('dropzone'))
            $(ev.target).css('background-color', '');
        jmqtt_globals.dropzoneCpt--;
        if (jmqtt_globals.dropzoneCpt <= 0) {
            $('.dropzone').hide();
            jmqtt_globals.dropzoneCpt = 0;
        }
    }
}

// On drop of a certificate file in a Broker dropzone
jmqtt.certDrop = function(ev) {
    ev.preventDefault();
    ev.stopPropagation();
    $('.dropzone').hide();
    var dropzone = $(this);
    if (dropzone.hasClass('dropzone')) {
        var reader = new FileReader();
        reader.onloadend = function(ev) {
            if (this.result.includes('-----BEGIN')) {
                dropzone.next().val(this.result);
                dropzone.css('background-color', 'palegreen').show().fadeOut(600);
            } else
                dropzone.css('background-color', 'orangered').show().fadeOut(800);
        };
        reader.readAsText(ev.originalEvent.dataTransfer.files[0], "UTF-8");
    }
    jmqtt_globals.dropzoneCpt = 0;
}

// On click on upload certificate file in a Broker uploadzone
jmqtt.certUpload = function(ev1) {
    var uploadzone = $(this);
    var fileDialog = $('<input type="file" accept=".crt,.pem,.key">');
    fileDialog.click();
    fileDialog.on("change",function(ev2) {
        var reader = new FileReader();
        reader.onloadend = function(ev3) {
            if (this.result.includes('-----BEGIN')) {
                uploadzone.prev().val(this.result);
                uploadzone.removeClass('btn-default btn-danger').addClass('btn-success');
            } else
                uploadzone.removeClass('btn-default btn-success').addClass('btn-danger');
            setTimeout(function() { uploadzone.removeClass('btn-success btn-danger').addClass('btn-default'); }, 1500);
        };
        reader.readAsText($(this)[0].files[0], "UTF-8");
    });
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
    jmqtt_globals.mainTopic = mt.val();
    if (jmqtt_globals.mainTopic == '')
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
    if (jmqtt.isPageModified()) {
        bootbox.confirm("{{La page a été modifiée. Etes-vous sûr de vouloir la recharger sans sauver ?}}", function (result) {
            if (result)
                refreshPage();
        });
    }
    else
        refreshPage();
}

// On eqLogic, on Eqpt tab, setup Broker list, mainTopic, battery, availability, icon list
jmqtt.updateEqptTabs = function(_eq) {
    // Initialize the broker dropbox
    var brokers = $('.eqLogicAttr[data-l1key=configuration][data-l2key=eqLogic]');
    brokers.empty();
    $.each(jmqtt_globals.eqBrokers, function(key, name) {
        brokers.append(new Option(name, key));
    });
    if (_eq.configuration.eqLogic != undefined && _eq.configuration.eqLogic > 0)
        brokers.val(_eq.configuration.eqLogic);

    // Set mainTopic global
    jmqtt_globals.mainTopic = $('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').val();

    // Initialise battery and availability dropboxes
    var eqId = jmqtt.getEqId();
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
    bat.val(_eq.configuration.battery_cmd);
    avl.val(_eq.configuration.availability_cmd);

    // Set value
    $('.eqLogicAttr[data-l1key=configuration][data-l2key=icone]').value(_eq.configuration.icone); // Use .value() here, instead of .val(), to trigger change event

    // Update Real Time tab
    jmqtt.updateRealTimeTab(_eq.configuration.eqLogic, false);
}

// Decorator for Core plugin template on save callback
jmqtt.decorateSaveEqLogic = function (_realSave) {
    function wrapSaveEqLogic() {
        // No mismatch or broker
        if ($('.topicMismatch').length == 0 || $('.eqLogicAttr[data-l1key="configuration"][data-l2key="type"]').value() == 'broker') {
            jmqtt.unsetPageModified();
            _realSave();
            return;
        }
        // Alert user that there is a mismatch before saveEqLogic (on eqLogic, not on eqBroker)
        var dialog_message = '';
        var no_name = false;
        if (jmqtt_globals.mainTopic == '') {
            dialog_message += "{{Le topic principal de l'équipement (topic de souscription MQTT) est <b>vide</b> !}}<br/>";
        } else {
            dialog_message += "{{Le topic principal de l'équipement (topic de souscription MQTT) est}} \"<b>";
            dialog_message += jmqtt_globals.mainTopic;
            dialog_message += '</b>"<br/>{{Les commandes suivantes posent problème :}}<br/><br/>';
            $('.topicMismatch').each(function (_, item) {
                if ($(item).hasClass('eqLogicAttr'))
                    return;
                let line = $(item).closest('tr.cmd');
                let cmd = line.find('.cmdAttr[data-l1key=name]').value();
                let cmdType = (line.find('.cmdAttr[data-l1key=type]').value() == 'info') ? '{{Le topic de souscription}}' : '{{Le topic de publication}}';
                // Command info has no name
                if (cmd == '')
                    no_name = true;
                var topic = $(item).value();
                // Command with a name and no topic
                if (cmd != '' && topic == '')
                    dialog_message += '<li>' + cmdType + ' {{est <b>vide</b> sur la commande}} "<b>' + cmd + '</b>"</li>';
                // Command with no name and a topic
                else if (cmd == '' && topic != '')
                    dialog_message += '<li>' + cmdType + ' "<b>' + topic + '</b>" {{sur une <b>commande sans nom</b>}}</li>';
                // Command with a mismatch
                else
                    dialog_message += '<li>' + cmdType + ' "<b>' + topic + '</b>" {{sur la commande}} "<b>' + cmd + '</b>"</li>';
            });
        }
        if (no_name)
            dialog_message += "<br/>{{(Notez que les commandes sans nom seront supprimées lors de la sauvegarde)}}";
        dialog_message += "<br/>{{Souhaitez-vous tout de même sauvegarder l'équipement ?}}";
        bootbox.confirm({
            title: "<b>{{Des problèmes ont été identifiés dans la configuration}}</b>",
            message: dialog_message,
            callback: function (result) { if (result) { // Do save
                jmqtt.unsetPageModified();
                _realSave();
            }}
        });
    }
    return wrapSaveEqLogic;
}


///////////////////////////////////////////////////////////////////////////////////////////////////
// Used by Real Time

// Display an Alert if Real time mode has changed for this eqBroker
jmqtt.displayRealTimeEvent = function(_eq) {
    // Fetch current Real Time mode for this Broker
    let realtime = $('.eqLogicDisplayCard[jmqtt_type=broker][data-eqlogic_id=' + _eq.id + ']').find('span.hiddenAsTable i.rt-status');
    if (_eq.cache.realtime_mode == '1') {
        if (realtime.hasClass('fa-square')) {
            // Show an alert only if Real time mode was disabled
            $.fn.showAlert({message: `{{Mode Temps Réel sur le Broker <b>${_eq.name}</b> pendant ${_eq.cache.mqttDuration} secondes.}}`, level: 'warning'});
        }
    } else if (realtime.hasClass('fa-sign-in-alt')) {
        // Show an alert only if Real time mode was enabled
        $.fn.showAlert({message: `{{Fin du mode Temps Réel sur le Broker <b>${_eq.name}</b>.}}`, level: 'warning'});
    }
}

// Helper to show/hide/disable Real Time buttons
jmqtt.updateRealTimeTab = function(id, paused) {
    var eqCard = $('.eqLogicDisplayCard[jmqtt_type=broker][data-eqlogic_id="' + id + '"]');
    // Get Real Time mode values
    $('#mqttIncTopic').value(eqCard[0].dataset.rtInc != undefined ? eqCard[0].dataset.rtInc : '#');
    $('#mqttExcTopic').value(eqCard[0].dataset.rtExc != undefined ? eqCard[0].dataset.rtExc : 'homeassistant/#');
    $('#mqttRetTopic').prop('checked', eqCard[0].dataset.rtRet == '1' ? eqCard[0].dataset.rtRet : false);
    $('#mqttDuration').value(eqCard[0].dataset.rtDur != undefined ? eqCard[0].dataset.rtDur : '180');
    clearInterval(jmqtt_globals.refreshRealTime);
    // Load Real Time Data every 2s
    if (!paused) {
        jmqtt_globals.refreshRealTime = setInterval(function() {
                // If Real Time table is visible and no data is visible, load it
                if ($('#table_realtime:visible').length != 0 && eqCard[0].dataset.rtSince == undefined) {
                    jmqtt.getRealTimeData();
                // If Real Time mode is active, load more data
                } else if ($('.eqLogicAction[data-action=stopRealTimeMode]:visible').length) {
                    jmqtt.getRealTimeData();
                }
            }, 2000);
    }

    if (eqCard.hasClass('disableCard')) {           // Disable buttons if eqBroker is disabled
        $('.eqLogicAction[data-action=startRealTimeMode]').show().addClass('disabled');
        $('.eqLogicAction[data-action=stopRealTimeMode]').hide();
        $('.eqLogicAction[data-action=playRealTime]').hide();
        $('.eqLogicAction[data-action=pauseRealTime]').hide();
        $('#table_realtime thead input.form-control').attr('disabled', '');
    } else if (eqCard[0].dataset.rtRun != '1') {    // Show only startRealTimeMode button
        $('.eqLogicAction[data-action=startRealTimeMode]').show().removeClass('disabled');
        $('.eqLogicAction[data-action=stopRealTimeMode]').hide();
        $('.eqLogicAction[data-action=playRealTime]').hide();
        $('.eqLogicAction[data-action=pauseRealTime]').hide();
        $('#table_realtime thead input.form-control').removeAttr('disabled');
    } else if (paused) {                            // Show only stopRealTimeMode & playRealTimeMode button
        $('.eqLogicAction[data-action=startRealTimeMode]').hide();
        $('.eqLogicAction[data-action=stopRealTimeMode]').show();
        $('.eqLogicAction[data-action=playRealTime]').show();
        $('.eqLogicAction[data-action=pauseRealTime]').hide();
        $('#table_realtime thead input.form-control').attr('disabled', '');
    } else {                                        // Show stopRealTimeMode & pauseRealTimeMode button
        $('.eqLogicAction[data-action=startRealTimeMode]').hide();
        $('.eqLogicAction[data-action=stopRealTimeMode]').show();
        $('.eqLogicAction[data-action=playRealTime]').hide();
        $('.eqLogicAction[data-action=pauseRealTime]').show();
        $('#table_realtime thead input.form-control').attr('disabled', '');
    }

    // Display only relevant Real Time data
    $('#table_realtime').find('tr.rtCmd[data-brkId!="' + id + '"]').hide();
    $('#table_realtime').find('tr.rtCmd[data-brkId="' + id + '"]').show();
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
            retained: ($('#mqttRetTopic').is(':checked') ? 1 : 0),
            duration: $('#mqttDuration').val()
        }
    });
}

// Build a new line in Real Time tab of a eqBroker
jmqtt.newRealTimeCmd = function(_data) {
    var tr = '<tr class="rtCmd" data-brkId="' + _data.id + '"' + ((_data.id == jmqtt.getBrkId()) ? '' : ' style="display: none;"') + '>';
    tr += '<td class="fitwidth"><span class="cmdAttr">' + (_data.date ? _data.date : '') + '</span></td>';
    tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="topic" style="margin-bottom:5px;" value="' + _data.topic.replace(/"/g, '&quot;') + '" disabled>';
    tr += '<input class="cmdAttr form-control input-sm col-lg-11 col-md-10 col-sm-10 col-xs-10" style="float: right;" data-l1key="jsonPath" value="' + (_data.jsonPath ? _data.jsonPath : '') + '" disabled></td>';
    tr += '<td><textarea class="cmdAttr form-control input-sm" data-l1key="payload" style="min-height:65px;" readonly=true disabled>' + _data.payload + '</textarea></td>';
    tr += '<td align="center"><input class="cmdAttr form-control" data-l1key="qos" style="display:none;" value="' + _data.qos + '" disabled>';
    if (_data.retain)
        tr += '<i class="fas fa-database warning tooltips" title="{{Ce message est stocké sur le Broker (Retain)}}"></i>';
    if (_data.retain && _data.existing)
        tr += '<br/><br/>';
    if (_data.existing)
        tr += '<i class="fas fa-sign-in-alt fa-rotate-90 success tooltips" title="{{Ce topic est compatible avec le(s) équipement(s) :}}' + _data.existing + '"></i>';
    tr += '</td><td align="right"><div class="input-group pull-right" style="display:inline-flex">';
    tr += '<a class="btn btn-primary btn-sm roundedLeft cmdAction tooltips" data-action="addEq" title="{{Ajouter un nouvel équipement souscrivant à ce topic}}"><i class="fas fa-plus-circle"></i></a>';
    tr += '<a class="btn btn-success btn-sm cmdAction tooltips" data-action="addCmd" title="{{Ajouter à un équipement existant}}"><i class="far fa-plus-square"></i></a>';
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
    if (jmqtt_globals.lockRealTime)
        return;
    jmqtt_globals.lockRealTime = true;
    var broker = jmqtt.getBrkId()
    var eqData = $('.eqLogicDisplayCard[jmqtt_type=broker][data-eqlogic_id="' + broker + '"]')[0].dataset;
    if (eqData.rtSince == undefined) eqData.rtSince = 0;
    jmqtt.callPluginAjax({
        data: {
            action: "realTimeGet",
            id: broker,
            since: eqData.rtSince
        },
        error: function (error) {
            $.fn.showAlert({message: error.message, level: 'danger'});
            jmqtt_globals.lockRealTime = false;
        },
        success: function (data) {
            if (data.length > 0) {
                let realtime = '';
                for (var i in data) {
                    data[i].id = broker;
                    realtime = jmqtt.newRealTimeCmd(data[i]) + realtime;
                    eqData.rtSince = data[i].date;
                }
                $('#table_realtime tbody').prepend(realtime);
            }
            jmqtt_globals.lockRealTime = false;
        }
    });
}

// Function to populate eq list with eqLogics that belongs to the selected Object
jmqtt.addCmdOnObjChange = function(_objId) {
    jeedom.object.getEqLogic({
        id: (_objId == '' ? -1 : _objId),
        orderByName : true,
        error: function(error) {
            $.fn.showAlert({message: error.message, level: 'danger'});
        },
        success: function(eqLogics) {
            $('#md_addJmqttCmdTable td.md_addJmqttCmdValeqL').empty();
            var selectEqLogic = '<select class="form-control">';
            var curBrk = jmqtt.getBrkId();
            var curId = jmqtt.getEqId()
            for (var i in eqLogics) {
                if (eqLogics[i].eqType_name                  == 'jMQTT'
                        && eqLogics[i].configuration.type    == 'eqpt'
                        && eqLogics[i].configuration.eqLogic == curBrk) {
                    if (eqLogics[i].id == curId) // Pre-select current eqLogic on load if present in the list
                        selectEqLogic += '<option value="' + eqLogics[i].id + '" selected>' + eqLogics[i].name + '</option>';
                    else
                        selectEqLogic += '<option value="' + eqLogics[i].id + '">' + eqLogics[i].name + '</option>';
                }
            }
            selectEqLogic += '</select>';
            $('#md_addJmqttCmdTable td.md_addJmqttCmdValeqL').append(selectEqLogic);
        }
    });
}

// Open modal to add an eq from Real Time tab
jmqtt.addEqFromRealTime = function(topic, jsonPath) {
    var broker    = jmqtt.getBrkId();
    var topicTab  = topic.split('/').filter(t => t.trim().length > 0);
    var eqName    = topicTab.shift();
    var mainTopic = (topic[0] == '/' ? '/' : '') + eqName + '/#';

    var dialog_message = '<label class="control-label">{{Nom du nouvel équipement :}}</label> ';
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="addJmqttEqName" value="' + eqName + '"><br/><br/>';
    dialog_message += '<label class="control-label">{{Topic de souscription du nouvel équipement :}}</label> ';
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="addJmqttEqTopic" value="' + mainTopic + '"><br/><br/>';
    dialog_message += '<label class="control-label checkbox-inline"><input type="checkbox" class="bootbox-input bootbox-checkbox-inline" id="addJmqttAuto"  checked/> ';
    dialog_message += '{{Ajout automatique des nouvelles commandes sur cet équipement}}</label><br/><br/>';

    // Display new EqLogic modal
    bootbox.confirm({
        title: '{{Ajouter une nouvelle commande sur un nouvel équipement}}',
        message: dialog_message,
        callback: function (result){ if (result) {
            eqName = $('#addJmqttEqName').value();
            if (eqName === undefined || eqName == null || eqName === '' || eqName == false) {
                $.fn.showAlert({message: "{{Le nom de l'équipement ne peut pas être vide !}}", level: 'warning'});
                return false;
            }
            mainTopic = $('#addJmqttEqTopic').value();
            if (mainTopic === undefined || mainTopic == null || mainTopic === '' || mainTopic == false) {
                $.fn.showAlert({message: "{{Le topic de souscription du nouvel équipement ne peut pas être vide !}}", level: 'warning'});
                return false;
            }
            var autoAdd = $('#addJmqttAuto').value();

            // Create a new eqLogic
            jeedom.eqLogic.save({
                type: 'jMQTT',
                eqLogics: [ {name: eqName, isEnable: '1', autoAddCmd: autoAdd, type: 'eqpt', eqLogic: broker, topic: mainTopic} ],
                error: function (error) {
                    $.fn.showAlert({message: error.message, level: 'danger'});
                },
                success: function (dataEq) {
                    $.fn.showAlert({message: `{{Le nouvel équipement <b>${dataEq.name}</b> a bien été ajoutée.}}`, level: 'success'});
                }
            });

        }}
    });
}

// TODO: Check if `jmqtt.addEqCmdFromRealTime` can be removed
//  labels: quality, javascript
/*
// Open modal to add an eq + cmd from Real Time tab !!! UNUSED !!!
jmqtt.addEqCmdFromRealTime = function(topic, jsonPath) {
    var broker    = jmqtt.getBrkId();
    var topicTab  = topic.split('/').filter(t => t.trim().length > 0);
    var eqName    = topicTab.shift();
    var mainTopic = (topic[0] == '/' ? '/' : '') + eqName + '/#';
    var cmdName   = topicTab.join(':') + jsonPath.replaceAll(']', '').replaceAll('[', ':');

    var dialog_message = '<label class="control-label">{{Nom du nouvel équipement :}}</label> ';
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="addJmqttEqName" value="' + eqName + '"><br/><br/>';
    dialog_message += '<label class="control-label">{{Topic de souscription du nouvel équipement :}}</label> '
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="addJmqttEqTopic" value="' + mainTopic + '"><br/><br/>'
    dialog_message += '<label class="control-label">{{Nom de la nouvelle commande :}}</label> '
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="addJmqttCmdName" value="' + cmdName + '"><br/><br/>'

    // Display new EqLogic modal
    bootbox.confirm({
        title: '{{Ajouter une nouvelle commande sur un nouvel équipement}}',
        message: dialog_message,
        callback: function (result){ if (result) {
            eqName = $('#addJmqttEqName').value();
            if (eqName === undefined || eqName == null || eqName === '' || eqName == false) {
                $.fn.showAlert({message: "{{Le nom de l'équipement ne peut pas être vide !}}", level: 'warning'});
                return false;
            }
            mainTopic = $('#addJmqttEqTopic').value();
            if (mainTopic === undefined || mainTopic == null || mainTopic === '' || mainTopic == false) {
                $.fn.showAlert({message: "{{Le topic de souscription du nouvel équipement ne peut pas être vide !}}", level: 'warning'});
                return false;
            }
            cmdName = $('#addJmqttCmdName').value();
            if (cmdName === undefined || cmdName == null || cmdName === '' || cmdName == false) {
                $.fn.showAlert({message: "{{Le nom de la commande ne peut pas être vide !}}", level: 'warning'});
                return false;
            }

            // Create a new eqLogic
            jeedom.eqLogic.save({
                type: 'jMQTT',
                eqLogics: [ {name: eqName, isEnable: '1', type: 'eqpt', eqLogic: broker, topic: mainTopic} ],
                error: function (error) {
                    $.fn.showAlert({message: error.message, level: 'danger'});
                },
                success: function (dataEq) {

                    // Create a new jMQTTCmd
                    jmqtt.callPluginAjax({
                        data: {
                            action: "newCmd",
                            id: dataEq.id,
                            name: cmdName,
                            topic: topic,
                            jsonPath: jsonPath
                        },
                        error: function (error) {
                            $.fn.showAlert({message: error.message, level: 'danger'});
                        },
                        success: function (data) {
                            $.fn.showAlert({message: `{{La commande <b>${cmdName}</b> a bien été ajoutée sur le nouvel équipement <b>${dataEq.name}</b>.}}`, level: 'success'});
                        }
                    });
                }
            });

        }}
    });
}
*/

// Open modal to add a cmd from Real Time tab
jmqtt.addCmdFromRealTime = function(topic, jsonPath) {
    var topicTab  = topic.replaceAll('"', '').split('/').filter(t => t.trim().length > 0);
    topicTab.shift();
    var cmdName   = topicTab.join(':') + jsonPath.replaceAll(']', '').replaceAll('"', '').replaceAll('[', ':');

    // Build Object list for new cmd creation modal
    jeedom.object.getUISelectList({
        none: 0,
        error: function(error) {
            $.fn.showAlert({message: error.message, level: 'danger'})
        },
        success: function(_objectsList) {
            var msg = '<table class="table table-condensed table-bordered" id="md_addJmqttCmdTable">';
            msg += '<thead><tr><th style="width: 150px;">{{Objet}}</th><th style="width: 150px;">{{Équipement}}</th></tr></thead>';
            msg += '<tbody><tr><td class="md_addJmqttCmdValObj">';
            msg += '<select class="form-control"><option value="">{{Aucun}}</option>' + _objectsList + '</select>';
            msg += '</td><td class="md_addJmqttCmdValeqL"></td></tr></tbody></table><br/>';
            msg += '<label class="control-label">{{Nom de la nouvelle commande :}}</label> ';
            msg += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="addJmqttCmdName" value="' + cmdName + '"><br/><br/>';
            // Display new cmd creation modal with Eq selector
            bootbox.confirm({
                title: '{{Ajouter cette commande à un equipement existant}}',
                message: msg,
                callback: function (result){ if (result) {
                    // Fetch selected destination equipment
                    var eqptId = $('.md_addJmqttCmdValeqL select').value()
                    // Check if destination equipment is usable
                    if (eqptId == 0 || eqptId == undefined) {
                        $.fn.showAlert({message: "{{Merci de sélectionner un équipement sur lequel créer cette commande !}}", level: 'warning'});
                        return false;
                    }
                    // Check if cmd Name is OK
                    cmdName = $('#addJmqttCmdName').value();
                    if (cmdName === undefined || cmdName == null || cmdName === '' || cmdName == false) {
                        $.fn.showAlert({message: "{{Le nom de la commande ne peut pas être vide !}}", level: 'warning'});
                        return false;
                    }
                    // Create a new jMQTTCmd
                    jmqtt.callPluginAjax({
                        data: {
                            action: "newCmd",
                            id: eqptId,
                            name: cmdName,
                            topic: topic,
                            jsonPath: jsonPath
                        },
                        error: function (error) {
                            $.fn.showAlert({message: error.message, level: 'danger'});
                        },
                        success: function (data) {
                            $.fn.showAlert({message: `{{La commande <b>${data.human}</b> a bien été ajoutée.}}`, level: 'success'});
                        }
                    });

                }}
            });
            var objSelect = $('#md_addJmqttCmdTable td.md_addJmqttCmdValObj select');
            // Just afer modal creation, add a callback on object select change
            objSelect.off('change').on('change', function(event) {
                jmqtt.addCmdOnObjChange($(this).value());
            });
            // Pre select current object in the object select
            objSelect.value($('.eqLogicAttr[data-l1key=object_id]').value());
        }
    });
}
