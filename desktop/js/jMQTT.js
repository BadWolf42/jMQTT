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

// TODO: Remove jQuery from jMQTT
//  labels: enhancement, help wanted, javascript

///////////////////////////////////////////////////////////////////////////////////////////////////
// Actions on main plugin view
//
$('.eqLogicAction[data-action=addJmqttBrk]').off('click').on('click', function () {
    bootbox.prompt("{{Nom du nouveau broker ?}}", function (result) {
        if (result !== null) {
            jeedom.eqLogic.save({
                type: 'jMQTT',
                eqLogics: [ $.extend({name: result}, {type: 'broker', eqLogic: -1, configuration: {Qos:"1", mqttProto:"mqtt"}}) ],
                error: function (error) {
                    $.fn.showAlert({message: error.message, level: 'danger'});
                },
                success: function (data) {
                    var url = jmqtt.initPluginUrl();
                    jmqtt.unsetPageModified();
                    url += '&id=' + data.id + '&saveSuccessFull=1';
                    jeedomUtils.loadPage(url);
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
    $('#md_modal').dialog({title: "Debug jMQTT"});
    $('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=debug').dialog('open');
});

$('.eqLogicAction[data-action=templatesMQTT]').on('click', function () {
    $('#md_modal').dialog({title: "{{Gestion des templates d'équipements}}"});
    $('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=templates').dialog('open');
});

$('.eqLogicAction[data-action=addJmqttEq]').off('click').on('click', function () {
    var dialog_message = '<label class="control-label">{{Broker utilisé :}}</label> ';
    dialog_message += '<select class="bootbox-input bootbox-input-select form-control" id="addJmqttBrkSelector">';
    $.each(jmqtt_globals.eqBrokers, function(key, name) { dialog_message += '<option value="'+key+'">'+name+'</option>'; });
    dialog_message += '</select><br/>';
    dialog_message += '<label class="control-label">{{Nom du nouvel équipement :}}</label> ';
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="addJmqttEqName"><br/><br/>';
    dialog_message += '<label class="control-label">{{Utiliser un template :}}</label> ';
    dialog_message += '<select class="bootbox-input bootbox-input-select form-control" id="addJmqttTplSelector">';
    dialog_message += '</select><br/>';
    dialog_message += '<label class="control-label" style="display:none;" id="addJmqttTplText">{{Saisissez le Topic de base :}}</label> ';
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" style="display:none;" autocomplete="nope" type="text" id="addJmqttTplTopic"><br/>';
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
            var eqTemplate = $('#addJmqttTplSelector').val();
            var eqTopic = $('#addJmqttTplTopic').val();
            if (eqTemplate != '' && eqTopic == '') {
                $.fn.showAlert({message: "{{Si vous souhaitez appliquer un template, le Topic de base ne peut pas être vide !}}", level: 'warning'});
                return false;
            }
            jeedom.eqLogic.save({
                type: 'jMQTT',
                eqLogics: [ $.extend({name: eqName}, {type: 'eqpt', eqLogic: broker}) ],
                error: function (error) {
                    $.fn.showAlert({message: error.message, level: 'danger'});
                },
                success: function (savedEq) {
                    if (eqTemplate != '') {
                        jmqtt.callPluginAjax({
                            data: {
                                action: "applyTemplate",
                                id: savedEq.id,
                                name : eqTemplate,
                                topic: eqTopic,
                                keepCmd: false
                            },
                            success: function (dataresult) {
                                var url = jmqtt.initPluginUrl();
                                jmqtt.unsetPageModified();
                                url += '&id=' + savedEq.id + '&saveSuccessFull=1';
                                jeedomUtils.loadPage(url);
                            }
                        });
                    } else {
                        var url = jmqtt.initPluginUrl();
                        jmqtt.unsetPageModified();
                        url += '&id=' + savedEq.id + '&saveSuccessFull=1';
                        jeedomUtils.loadPage(url);
                    }
                }
            });
        }}
    });
    $('#addJmqttTplSelector').on('change', function() {
        if ($(this).val() == '') {
            $('#addJmqttTplText').hide();
            $('#addJmqttTplTopic').hide();
        } else {
            $('#addJmqttTplText').show();
            $('#addJmqttTplTopic').show();
        }
    });
    jmqtt.callPluginAjax({
        data: {
            action: "getTemplateList",
        },
        error: function(error) {},
        success: function (dataresult) {
            opts = '<option value="">{{Aucun}}</option>';
            for (var i in dataresult)
                opts += '<option value="' + dataresult[i][0] + '">' + dataresult[i][0] + '</option>';
            $('#addJmqttTplSelector').html(opts);
        }
    });
});


///////////////////////////////////////////////////////////////////////////////////////////////////
// Modals associated to buttons "Rechercher équipement" for Action and Info Cmd
//

$("#table_cmd").delegate(".listEquipementInfo", 'click', function () {
    var el = $(this);
    jeedom.cmd.getSelectModal({cmd: {type: 'info'}}, function (result) {
        var calcul = el.closest('tr').find('.cmdAttr[data-l1key=configuration][data-l2key=' + el.data('input') + ']');
        calcul.atCaret('insert', result.human);
        jmqtt.setPageModified();
    });
});


///////////////////////////////////////////////////////////////////////////////////////////////////
// Actions on Broker tab
//
$('.eqLogicAction[data-action=startMqttClient]').on('click',function(){
    var id = jmqtt.getEqId();
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


///////////////////////////////////////////////////////////////////////////////////////////////////
// Automations on Broker tab attributes
//
$('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttProto]').change(function(){
    switch ($(this).val()) {
        case 'mqtts':
            $('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttPort]').addClass('roundedRight').attr('placeholder', '8883');
            $('.jmqttWsUrl').hide();
            $('#jmqttTls').show();
            break;
        case 'ws':
            $('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttPort]').removeClass('roundedRight').attr('placeholder', '1884');
            $('.jmqttWsUrl').show();
            $('#jmqttTls').hide();
            break;
        case 'wss':
            $('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttPort]').removeClass('roundedRight').attr('placeholder', '8884');
            $('.jmqttWsUrl').show();
            $('#jmqttTls').show();
            break;
        default: // mqtt
            $('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttPort]').addClass('roundedRight').attr('placeholder', '1883');
            $('.jmqttWsUrl').hide();
            $('#jmqttTls').hide();
            break;
    }
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

$('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttId]').change(function(){
    if ($(this).value() == '1')
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttIdValue]').show();
    else
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=mqttIdValue]').hide();
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


///////////////////////////////////////////////////////////////////////////////////////////////////
// Actions on Real Time tab attributes
//
$('#table_realtime').on('click', '.eqLogicAction[data-action=startRealTimeMode]', function() {
    // Enable Real Time mode for a Broker
    jmqtt.changeRealTimeMode(jmqtt.getBrkId(), 1);
});

$('#table_realtime').on('click', '.eqLogicAction[data-action=stopRealTimeMode]', function() {
    // Disable Real Time mode for a Broker
    jmqtt.changeRealTimeMode(jmqtt.getBrkId(), 0);
});

$('#table_realtime').on('click', '.eqLogicAction[data-action=playRealTime]', function() {
    // Restarts Real Time mode view
    jmqtt.updateRealTimeTab(jmqtt.getBrkId(), false);
});

$('#table_realtime').on('click', '.eqLogicAction[data-action=pauseRealTime]', function() {
    // Pause Real Time mode view
    jmqtt.updateRealTimeTab(jmqtt.getBrkId(), true);
});

// Button to empty RealTime view
$('#table_realtime').on('click', '.eqLogicAction[data-action=emptyRealTime]', function() {
    // Ask Daemon to cleanup its Real Time database
    var broker = jmqtt.getBrkId();
    jmqtt.callPluginAjax({
        data: {
            action: "realTimeClear",
            id: broker
        },
        error: function (error) {
            $.fn.showAlert({message: error.message, level: 'danger'});
        },
        success: function (data) {
            $('#table_realtime tbody .rtCmd[data-brkId=' + broker + ']').remove();
        }
    });
})

// Button to add a new eq and cmd from Real Time tab
$('#table_realtime').on('click', '.cmdAction[data-action=addEq]', function() {
    var topic    = $(this).closest('tr').find('.cmdAttr[data-l1key=topic]').val();
    var jsonPath = $(this).closest('tr').find('.cmdAttr[data-l1key=jsonPath]').val();
    jmqtt.addEqFromRealTime(topic, jsonPath);
})

// Button to add a cmd from Real Time tab
$('#table_realtime').on('click', '.cmdAction[data-action=addCmd]', function() {
    var topic    = $(this).closest('tr').find('.cmdAttr[data-l1key=topic]').val();
    var jsonPath = $(this).closest('tr').find('.cmdAttr[data-l1key=jsonPath]').val();
    jmqtt.addCmdFromRealTime(topic, jsonPath);
})

$('#table_realtime').on('click', '.cmdAction[data-action=splitJson]', function() {
    $(this).removeClass('btn-warning').addClass('btn-default disabled');
    var tr = $(this).closest('tr');
    var payload = tr.find('.cmdAttr[data-l1key=payload]').text();
    var json = jmqtt.toJson(payload);
    if (typeof(json) !== 'object')
        return;
    var id = tr.attr('data-brkId');
    var topic = tr.find('.cmdAttr[data-l1key=topic]').val();
    var jsonPath = tr.find('.cmdAttr[data-l1key=jsonPath]').val();
    var qos = tr.find('.cmdAttr[data-l1key=qos]').value();

    for (item in json) {
        var _data = {id: id, topic: topic, payload: JSON.stringify(json[item]), qos: qos, retain: false};
        if (item.match(/[^\w-]/)) // Escape if a special character is found
            item = '\'' + item.replace(/'/g,"\\'") + '\'';
        _data.jsonPath = jsonPath + '[' + item + ']';
        var new_tr = jmqtt.newRealTimeCmd(_data);
        tr.after(new_tr);
        tr = tr.next();
    }
})

$('#table_realtime').on('click', '.cmdAction[data-action=remove]', function() {
    $(this).closest('tr').remove();
})

///////////////////////////////////////////////////////////////////////////////////////////////////
// Automations on Equipment tab attributes
//

// Show top menu on commandtab only
$('.nav-tabs a[href]').on('click', function() {
    if ($(this).attr('href') == '#commandtab')
        $('#menu-bar').show();
    else
        $('#menu-bar').hide();
});

// On eqLogic subscription topic field typing
$('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').on('input', function() {
    // Update mismatch status of this field only (cmd check will be done when leaving the field)
    jmqtt.updateGlobalMainTopic();
});

// On eqLogic subscription topic field set (initial set and finish typing)
$('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').on('change', function() {
    // Update mismatch status of this field
    jmqtt.updateGlobalMainTopic();
    // Update mismatch status of all cmd
    $('input.cmdAttr[data-l1key=configuration][data-l2key=topic]').each(function() {
        if (jmqtt.checkTopicMatch(jmqtt_globals.mainTopic, $(this).value()))
            $(this).removeClass('topicMismatch');
        else
            $(this).addClass('topicMismatch');
    });
});

// On eqLogic subscription topic field typing
$('.eqLogicAttr[data-l1key=configuration][data-l2key=auto_add_topic]').off('dblclick').on('dblclick', function() {
    if($(this).val() == "") {
        var objectname = $('.eqLogicAttr[data-l1key=object_id] option:selected').text();
        var eqName = $('.eqLogicAttr[data-l1key=name]').value();
        $(this).val(objectname.trim()+'/'+eqName+'/#');
        // Update mismatch status of this field only (cmd check will be done when leaving the field)
        jmqtt.updateGlobalMainTopic();
    }
});

// On eqLogic logo field change
$('.eqLogicAttr[data-l1key=configuration][data-l2key=icone]').off('change').on('change', function(e) {
    // Initialize once the Logo dropbox from logos global table
    // Get icon name
    var elt = ($('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val() == 'broker') ? 'broker' : $(this).select().val();
    // Get icon file
    var logo = (elt == undefined) ? 'plugins/jMQTT/core/img/node_.svg' : 'plugins/jMQTT/core/img/node_' + elt + '.svg';
    if ($("#logo_visu").attr("src") != logo) {
        $("#logo_visu").attr("src", logo);
    }
});

// Configure the sortable functionality of the commands array
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

// Restrict "cmd.configure" modal popup when double-click on command without id
$('#table_cmd').on('dblclick', '.cmd[data-cmd_id=""]', function(event) {
    event.stopPropagation()
});


///////////////////////////////////////////////////////////////////////////////////////////////////
// Actions in top menu on an Equipment
//

// On applyTemplate click
$('.eqLogicAction[data-action=applyTemplate]').off('click').on('click', function () {
    jmqtt.callPluginAjax({
        data: {
            action: "getTemplateList",
        },
        success: function (dataresult) {
            var dialog_message = '<label class="control-label">{{Choisissez un template :}}</label> ';
            dialog_message += '<select class="bootbox-input bootbox-input-select form-control" id="applyTemplateSelector">';
            for(var i in dataresult){ dialog_message += '<option value="'+dataresult[i][0]+'">'+dataresult[i][0]+'</option>'; }
            dialog_message += '</select><br/>';

            dialog_message += '<label class="control-label">{{Saisissez le Topic de base :}}</label> ';
            var currentTopic = jmqtt_globals.mainTopic;
            if (currentTopic.endsWith("#") || currentTopic.endsWith("+"))
                currentTopic = currentTopic.substr(0,currentTopic.length-1);
            if (currentTopic.endsWith("/"))
                currentTopic = currentTopic.substr(0,currentTopic.length-1);
            dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="applyTemplateTopic" value="'+currentTopic+'"><br/><br/>'
            dialog_message += '<label class="control-label">{{Que voulez-vous faire des commandes existantes ?}}</label> ';
            dialog_message += '<div class="radio"><label><input type="radio" name="applyTemplateCommand" value="1" checked="checked">{{Les conserver / Mettre à jour}}</label></div>';
            dialog_message += '<div class="radio"><label><input type="radio" name="applyTemplateCommand" value="0">' + "{{Les supprimer d'abord}}" + '</label></div>';

            bootbox.confirm({
                title: '{{Appliquer un Template}}',
                message: dialog_message,
                callback: function (result){ if (result) {
                    jmqtt.callPluginAjax({
                        data: {
                            action: "applyTemplate",
                            id: jmqtt.getEqId(),
                            name : $("#applyTemplateSelector").val(),
                            topic: $("#applyTemplateTopic").val(),
                            keepCmd: $("[name='applyTemplateCommand']:checked").val()
                        },
                        success: function (dataresult) {
                            $('.eqLogicDisplayCard[data-eqLogic_id=' + jmqtt.getEqId() + ']').click();
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
                        id: jmqtt.getEqId(),
                        name : result
                    },
                    success: function (dataresult) {
                        $('.eqLogicDisplayCard[data-eqLogic_id=' + jmqtt.getEqId() + ']').click();
                    }
                });
            }
        }
    });
});

// On updateTopics click
$('.eqLogicAction[data-action=updateTopics]').off('click').on('click', function () {
    var dialog_message = '<label class="control-label">{{Rechercher :}}</label> ';
    var currentTopic = jmqtt_globals.mainTopic;
    if (currentTopic.endsWith("#") || currentTopic.endsWith("+"))
        currentTopic = currentTopic.substr(0,currentTopic.length-1);
    if (currentTopic.endsWith("/"))
        currentTopic = currentTopic.substr(0,currentTopic.length-1);
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="oldTopic" value="'+currentTopic+'"><br/><br/>';
    dialog_message += '<label class="control-label">{{Replacer par :}}</label> ';
    dialog_message += '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" type="text" id="newTopic"><br/><br/>';
    dialog_message += '<label class="control-label">(' + "{{Pensez à sauvegarder l'équipement pour appliquer les modifications}}" + ')</label>';
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
            jmqtt.setPageModified();
        }}
    });
});

// On jsonPathTester click
$('.eqLogicAction[data-action=jsonPathTester]').off('click').on('click', function () {
    $('#md_modal').dialog({title: "{{Testeur de Chemin JSON}}"});
    $('#md_modal').load('index.php?v=d&plugin=jMQTT&modal=jsonPathTester').dialog('open');
});

// On addMQTTInfo click
$('.eqLogicAction[data-action=addMQTTInfo]').on('click', function() {
    var _cmd = {type: 'info'};
    addCmdToTable(_cmd);
    jmqtt.setPageModified();
});

// On addMQTTAction click
$('.eqLogicAction[data-action=addMQTTAction]').on('click', function() {
    var _cmd = {type: 'action'};
    addCmdToTable(_cmd);
    jmqtt.setPageModified();
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


///////////////////////////////////////////////////////////////////////////////////////////////////
// Standard Jeedom callback functions
//

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
                    state: val
                }, parent_id);
            }
            else {
                c.state = val;
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
                    if (c.state == undefined) {
                        console.log('missed c', c.id);
                        jeedom.cmd.execute({
                            async: false, id: c.id, cache: 0, notify: false,
                            success: function(result) {
                                c.state = result;
                            }});
                    }
                    try {
                        var parsed_json_value = JSON.parse(c.state);
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
        jmqtt.setCmdsSortable(false);
    } else {
        // CLASSIC view button is active
        for (var c of _eqLogic.cmd) {
            c.tree_id = (n_cmd++).toString();
        }

        // Classical view: enable the sortable functionality
        jmqtt.setCmdsSortable(true);
    }

    // Show UI elements depending on the type
    if ((_eqLogic.configuration.type == 'eqpt' && (_eqLogic.configuration.eqLogic == undefined || _eqLogic.configuration.eqLogic < 0))
            || (_eqLogic.configuration.type != 'eqpt' && _eqLogic.configuration.type != 'broker')) { // Unknow EQ / orphan
        $('.toDisable').addClass('disabled');
        $('.typ-brk').hide();
        $('.typ-std').hide();
        $('.typ-brk-select').show();
        $('.eqLogicAction[data-action=configure]').addClass('roundedLeft');

        // Udpate panel as if on an eqLogic
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val('eqpt');
        jmqtt.updateEqptTabs(_eqLogic);
    }
    else if (_eqLogic.configuration.type == 'broker') { // jMQTT Broker
        $('.toDisable').removeClass('disabled');
        $('.typ-std').hide();
        $('.typ-brk').show();
        $('.eqLogicAction[data-action=configure]').addClass('roundedLeft');

        // Udpate panel on eqBroker
        jmqtt.updateBrokerTabs(_eqLogic);
    }
    else if (_eqLogic.configuration.type == 'eqpt') { // jMQTT Eq
        $('.toDisable').removeClass('disabled');
        $('.typ-brk').hide();
        $('.typ-std').show();
        $('.eqLogicAction[data-action=configure]').removeClass('roundedLeft');

        // Udpate panel on eqLogic
        jmqtt.updateEqptTabs(_eqLogic);
    }
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
        if ((_eqLogic.cmd[i].id == "" || _eqLogic.cmd[i].id === null) && _eqLogic.cmd[i].name == "") {
            _eqLogic.cmd.splice(i, 1);
        }
    }

    // if this eqLogic is not a broker
    if (_eqLogic.configuration.type != 'broker') {
        // get hidden settings for Broker and remove them of eqLogic
        _eqLogic = jmqtt.substractKeys(_eqLogic, $('#brokertab').getValues('.eqLogicAttr')[0]);
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

    // TODO: Merge Action & Info cmd generation code and reuse it in templates
    //  Fix disabled variable use (virtualAction never exists)
    //  labels: quality, javascript
    if (init(_cmd.type) == 'info') {
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
        tr += '<textarea class="form-control input-sm" data-key="value" style="min-height:62px;" ' + disabled + ' placeholder="{{Valeur}}" readonly=true></textarea>';
        tr += '</td><td>';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:60px;display:inline-block;">';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:60px;display:inline-block;">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="Unité" title="{{Unité}}" style="width:60px;display:inline-block;">';
        tr += '</td><td>';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span><br/> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span><br/> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span><br/> ';
        tr += '</td><td align="right">';
        // TODO: Add Advanced parameters modale on each cmd
        //  The modale should include:
        //  - autoPub,
        //  - Qos,
        //  - move topic to other eqLogic,
        //  - related discovery config,
        //  - etc
        //  labels: enhancement, javascript
        // tr += '<a class="btn btn-default btn-xs cmdAction tooltips" data-action="advanced" title="{{Paramètres avancés}}"><i class="fas fa-wrench"></i></a> ';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction tooltips" data-action="configure" title="{{Configurer}}"><i class="fas fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction tooltips" data-action="test" title="{{Tester}}"><i class="fas fa-rss"></i></a> ';
        }
        tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
        tr += '</td></tr>';

        $('#table_cmd tbody').append(tr);

        // Update mismatch status of this cmd on change and input
        $('#table_cmd [tree-id="' + _cmd.tree_id + '"] .cmdAttr[data-l1key=configuration][data-l2key=topic]').on('change input', function(e) {
            if (jmqtt.checkTopicMatch(jmqtt_globals.mainTopic, $(this).value()))
                $(this).removeClass('topicMismatch');
            else
                $(this).addClass('topicMismatch');
        });

        // Set cmdAttr values of cmd from json _cmd
        $('#table_cmd [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
        if (isset(_cmd.type))
            $('#table_cmd [tree-id="' + _cmd.tree_id + '"] .cmdAttr[data-l1key=type]').value(init(_cmd.type));
        jeedom.cmd.changeType($('#table_cmd [tree-id="' + _cmd.tree_id + '"]'), init(_cmd.subType));

        // Fill in value of current cmd. Efficient in JSON view only as _cmd.state was set in JSON view only in printEqLogic.
        if (is_json_view) {
            $('#table_cmd [tree-id="' + _cmd.tree_id + '"] .form-control[data-key=value]').value(_cmd.state);
        }

        // Get and display the value in CLASSIC view (for JSON view, see few lines above)
        if (_cmd.id != undefined) {
            if (! is_json_view) {
                if (_cmd.state != undefined) {
                    $('#table_cmd [tree-id="' + _cmd.tree_id + '"][data-cmd_id="' + _cmd.id + '"] .form-control[data-key=value]').value(_cmd.state);
                } else {
                    jeedom.cmd.execute({
                        id: _cmd.id,
                        cache: 0,
                        notify: false,
                        success: function(result) {
                            $('#table_cmd [tree-id="' + _cmd.tree_id + '"][data-cmd_id="' + _cmd.id + '"] .form-control[data-key=value]').value(result);
                    }});
                }
            }

            // Set the update value callback
            jeedom.cmd.addUpdateFunction(_cmd.id, function(_options) {
                $('#table_cmd [tree-id="' + _cmd.tree_id + '"][data-cmd_id="' + _cmd.id + '"] .form-control[data-key=value]').addClass('modifiedVal').value(_options.display_value);
                setTimeout(function() { $('#table_cmd [tree-id="' + _cmd.tree_id + '"][data-cmd_id="' + _cmd.id + '"] .form-control[data-key=value]').removeClass('modifiedVal'); }, 1500);
            });
        }

        $('#table_cmd [tree-id="' + _cmd.tree_id + '"]').show(); // SPEED Improvement : Create TR hiden then show it at the end after setValues, etc.
    }

    if (init(_cmd.type) == 'action') {
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
        tr += '<div class="input-group">';
        tr += '<textarea class="cmdAttr form-control input-sm roundedLeft" data-l1key="configuration" data-l2key="request" ' + disabled + ' style="min-height:62px;height:62px;" placeholder="Valeur"></textarea>';
        tr += '<a class="btn btn-sm btn-default listEquipementInfo input-group-addon roundedRight" title="{{Rechercher un équipement}}" data-input="request"><i class="fas fa-list-alt "></i></a>';
        tr += '</div>';
        tr += '</td><td>';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:60px;display:inline-block;">';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:60px;display:inline-block;">';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="listValue" placeholder="{{Liste : valeur|texte}}" title="{{Liste : valeur|texte (séparées entre elles par des points-virgules)}}">';
        tr += '</td><td>';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span><br/> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="retain"/>{{Retain}}</label></span><br/> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="autoPub"/>{{Pub. auto}}&nbsp;';
        tr += '<sup><i class="fas fa-question-circle tooltips" title="' + "{{Publication automatique en MQTT lors d'un changement <br/>(A utiliser avec au moins une commande info dans Valeur).}}" + '"></i></sup></label></span><br/> ';
        tr += '<span class="checkbox-inline">{{Qos}}: <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="Qos" placeholder="{{Qos}}" title="{{Qos}}" style="width:50px;display:inline-block;"></span> ';
        tr += '</td>';
        tr += '<td align="right">';
        // tr += '<a class="btn btn-default btn-xs cmdAction tooltips" data-action="advanced" title="{{Paramètres avancés}}"><i class="fas fa-wrench"></i></a> ';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction tooltips" data-action="configure" title="{{Configurer}}"><i class="fas fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction tooltips" data-action="test" title="{{Tester}}"><i class="fas fa-rss"></i></a> ';
        }
        tr += '<i class="fas fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
        tr += '</td></tr>';

        $('#table_cmd tbody').append(tr);

        // Update mismatch status of this cmd on change and input
        $('#table_cmd [tree-id="' + _cmd.tree_id + '"] .cmdAttr[data-l1key=configuration][data-l2key=topic]').on('change input', function(e) {
            let topic = $(this).value();
            if (topic == '' || topic.includes('#') || topic.includes('?'))
                $(this).addClass('topicMismatch');
            else
                $(this).removeClass('topicMismatch');
        });

        // $('#table_cmd [tree-id="' + _cmd.tree_id + '"]').setValues(_cmd, '.cmdAttr');
        var tr = $('#table_cmd [tree-id="' + _cmd.tree_id + '"]');
        jeedom.eqLogic.buildSelectCmd({
            id: jmqtt.getEqId(),
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

// TODO: Review events that should be sent to front-end
//  Change visual on dashboard on new events?
//  Replace/remove `jMQTT::EventState`?
//  Use `jMQTT::brkEvent`? `jMQTT::eqptEvent`? `jMQTT::cmdEvent`?
//  labels: enhancement, javascript
/*
$('body').off('jMQTT::brkEvent').on('jMQTT::brkEvent', function (_event,_options) {
    var msg = `{{La commande <b>${_options.name}</b> vient d'être ${_options.action}.}}`;
    console.log(msg, _options);
});

$('body').off('jMQTT::eqptEvent').on('jMQTT::eqptEvent', function (_event,_options) {
    var msg = `{{La commande <b>${_options.name}</b> vient d'être ${_options.action}.}}`;
    console.log(msg, _options);
});

$('body').off('jMQTT::cmdEvent').on('jMQTT::cmdEvent', function (_event,_options) {
    var msg = `{{La commande <b>${_options.name}</b> vient d'être ${_options.action}.}}`;
    console.log(msg, _options);
});
*/


///////////////////////////////////////////////////////////////////////////////////////////////////
// Events
//

/**
 * Called by the plugin core to inform about the creation of an equipment
 *
 * @param {string} _event event name (jMQTT::eqptAdded in this context)
 * @param {string} _options['eqlogic_name'] string name of the eqLogic command is added to
 */
$('body').off('jMQTT::eqptAdded').on('jMQTT::eqptAdded', function (_event, _options) {
    var msg = `{{L'équipement <b>${_options.eqlogic_name}</b> vient d'être ajouté}}`;

    // If the page is being modified or an equipment is being consulted or a dialog box is shown: display a simple alert message
    // Otherwise: display an alert message and reload the page
    if (jmqtt.isPageModified() || $('.eqLogic').is(":visible") || $('div[role="dialog"]').filter(':visible').length != 0) {
        $.fn.showAlert({message: msg + '.', level: 'warning'});
    }
    else {
        $.fn.showAlert({
            message: msg + '. {{La page va se réactualiser automatiquement}}.',
            level: 'warning'
        });
        // Reload the page after a delay to let the user read the message
        if (jmqtt_globals.refreshTimeout === undefined) {
            jmqtt_globals.refreshTimeout = setTimeout(function() {
                jmqtt_globals.refreshTimeout = undefined;
                window.location.reload();
            }, 3000);
        }
    }
});

/**
 * Management of the display when an information command is added
 * Triggerred when the plugin core send a jMQTT::cmdAdded event
 * @param {string} _event event name
 * @param {string} _options['eqlogic_name'] name of the eqLogic command is added to
 * @param {int} _options['eqlogic_id'] id of the eqLogic command is added to
 * @param {string} _options['cmd_name'] name of the new command
 * @param {bool} _options['reload'] whether or not a reload of the page is requested
 */
$('body').off('jMQTT::cmdAdded').on('jMQTT::cmdAdded', function(_event, _options) {
    var msg = `{{La commande <b>${_options.cmd_name}</b> est ajoutée à l'équipement <b>${_options.eqlogic_name}</b>.}}`;

    // If the page is being modified or another equipment is being consulted or a dialog box is shown: display a simple alert message
    if (jmqtt.isPageModified() || ( $('.eqLogic').is(":visible") && jmqtt.getEqId() != _options['eqlogic_id'] ) ||
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
        if (jmqtt_globals.refreshTimeout === undefined) {
            jmqtt_globals.refreshTimeout = setTimeout(function() {
                jmqtt_globals.refreshTimeout = undefined;
                $('.eqLogicAction[data-action=refreshPage]').click();
            }, 3000);
        }
    }
});

// Update the broker card and the real time mode display on reception of a new state event
$('body').off('jMQTT::EventState').on('jMQTT::EventState', function (_event, _eq) {
    var card = $('.eqLogicDisplayCard[jmqtt_type="broker"][data-eqlogic_id="' + _eq.id + '"]');
    if (!card.length) // Don't try to update anything when left jMQTT and js is still loaded
        return;
    // Display an alert if real time mode has changed on this Broker
    jmqtt.displayRealTimeEvent(_eq);
    // Update card on main page
    jmqtt.updateDisplayCard(card, _eq);
    // Update Panel and menu only when on the right Broker
    if (jmqtt.getEqId() == _eq.id)
        jmqtt.updateBrokerTabs(_eq);
    else if (jmqtt.getBrkId() == _eq.id) {
        jmqtt.updateRealTimeTab(_eq.id, false);
    }
});


///////////////////////////////////////////////////////////////////////////////////////////////////
// Apply some changes when document is loaded
//
$(document).ready(function() {
    // Done here, otherwise the refresh button remains selected
    $('.eqLogicAction[data-action=refreshPage]').removeAttr('href').off('click').on('click', function(event) {
        event.stopPropagation();
        jmqtt.refreshEqLogicPage();
    });

    //
    // update DisplayCards on main page at load
    //
    jeedom.eqLogic.byType({
        type: 'jMQTT',
        noCache: true,
        error: function (error) {
            $.fn.showAlert({message: error.message, level: 'warning'});
        },
        success: function(_eqLogics) {
            for (var i in _eqLogics)
                jmqtt.updateDisplayCard($('.eqLogicDisplayCard[data-eqlogic_id=' + _eqLogics[i].id + ']'), _eqLogics[i]);
        }
    });

    /*
     * Missing stopPropagation for span.hiddenAsCard in plugin main view
     * Without this, it is impossible to click on a link in table view without entering the equipement
     */
    $('.eqLogicDisplayCard').on('click', 'span.hiddenAsCard', function(event) {
        event.stopPropagation()
    });

    // Handle certificate file drap & drop
    if (window.FileReader) {
        $('html').on('dragenter dragover dragleave', jmqtt.certDrag).on('drop', jmqtt.certDrop);
        $('.dropzone').on('drop', jmqtt.certDrop);
        $('.uploadzone').on('click', jmqtt.certUpload);
    }

    // Wrap plugin.template save action handler
    if (typeof jeeFrontEnd.pluginTemplate === 'undefined') {
        // TODO: Remove core4.3 backward compatibility `saveEqLogic` js function
        //  Remove when Jeedom 4.3 is no longer supported
        //  labels: workarround, core4.3, javascript

        let core_save = $._data($('.eqLogicAction[data-action=save]')[0], 'events')['click'][0]['handler'];
        $('.eqLogicAction[data-action=save]').off('click').on('click', function() {
            jmqtt.decorateSaveEqLogic(core_save)();
        });
    } else {
        if (typeof jeeFrontEnd.pluginTemplate.oldSaveEqLogic === 'undefined') {
            let core_save = jeeFrontEnd.pluginTemplate.saveEqLogic;
            jeeFrontEnd.pluginTemplate.oldSaveEqLogic = core_save;
            jeeFrontEnd.pluginTemplate.saveEqLogic = jmqtt.decorateSaveEqLogic(core_save);
        }
    }
});
