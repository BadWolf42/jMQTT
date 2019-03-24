
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

//To memorise page refresh timeout when set
var refreshTimeout;

//Command number: used when displaying commands as a JSON tree.
var N_CMD;

///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//To debug browser history management (pushState, ...)  
//
//(function setOnPushStateFunction (window, history){
//    var pushState = history.pushState;
//    history.pushState = function(state) {
//        if (typeof window.onpushstate === 'function') {
//            window.onpushstate({ state: state });
//        }
//        return pushState.apply(history, arguments);
//    }
//})(window, window.history);
//
//
//window.onpushstate = function(event) {
//    console.log("onpushstate: location=" + document.location + ", state=" + JSON.stringify(event.state));
//};

// Workaround on Firefox : from times to times event.state is null which prevent the browser to reload the page
window.addEventListener("popstate", function(event) {
    if (event.state == null) {
        location.reload(false);
    }
});

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

//Function to refresh the page
//Ask confirmation if the page has been modified
function refreshEqLogicPage() {
    function refreshPage() {
        if ($('#ul_eqLogic .li_eqLogic.active').attr('data-eqLogic_id') != undefined)
            $('#ul_eqLogic .li_eqLogic.active').click();
        else
            $('.eqLogicAction[data-action=returnToThumbnailDisplay]').click();
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

$(document).ready(function() {
    // On page load, show the commandtab menu bar if necessary (fix #64)
    if (document.location.hash == '#commandtab') {
        $('#menu-bar').show();
    }
    
    history.replaceState({}, '', url);
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

$('.eqLogicAction[data-action=runDev]').on('click', function () {
    $.ajax({
        type: "POST", 
        url: "plugins/jMQTT/core/ajax/jMQTT.ajax.php", 
        data: {
            action: "dev"
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) { 
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
        }
    });
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

// Refresh the page on click on the refresh button, and classic and JSON button
$('.eqLogicAction[data-action=refreshPage]').on('click', refreshEqLogicPage);

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
    $('#menu-bar').show();
});

$('.nav-tabs a[role="tab"]').on('click', function() {
    if (document.location.hash != $(this)[0].hash) {
        url = initPluginUrl(['hash'], '', $(this)[0].hash);
        if (document.location.hash == '' && $(this)[0].hash == '#eqlogictab') {
            history.replaceState({hash: $(this)[0].hash}, '', url);
        }
        else {
            history.pushState({hash: $(this)[0].hash}, '', url);
        }
    }
    if ($(this)[0].hash == '#brokertab')
        refreshDaemonInfo();
});

// Manage the history on eqlogic display
$(".li_eqLogic,.eqLogicDisplayCard").on('click', function () {
    var url_id = getUrlVars('id');
    var id = $(this).attr('data-eqLogic_id');
    var hash = document.location.hash;
    if (!is_numeric(url_id) || url_id != id) {
        if (hash == '#brokertab' && $(this).attr('jmqtt_type') != 'broker')
            hash = '';
        url = initPluginUrl(['id', 'hash'], id, hash);
        history.pushState({}, '', url);
    }
    if (hash == '#brokertab')
        refreshDaemonInfo();
});

// Manage the history on return to the plugin page
$('.eqLogicAction[data-action=returnToThumbnailDisplay]').on('click', function () {
    url = initPluginUrl();
    history.pushState({}, '', url);
});

// Override plugin template to rewrite the URL to avoid keeping the successfull save message
if (getUrlVars('saveSuccessFull') == 1) {
    $('#div_alert').showAlert({message: '{{Sauvegarde effectuée avec succès}}', level: 'success'});
    history.replaceState({}, '', initPluginUrl(['saveSuccessFull']));
}

// Override plugin template to rewrite the URL to avoid keeping the successfull delete message
if (getUrlVars('removeSuccessFull') == 1) {
    $('#div_alert').showAlert({message: '{{Suppression effectuée avec succès}}', level: 'success'});
    history.replaceState({}, '', initPluginUrl(['removeSuccessFull']));
}

// Configure the sortable functionality of the commands array
$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});


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
    if (typeof $(this).attr('brkId') === 'undefined') {
        if ($('.eqLogicAttr[data-l1key=id]').value() != undefined) {
            bootbox.confirm('{{Etes-vous sûr de vouloir supprimer le broker}}' + ' <b>' + $('.eqLogicAttr[data-l1key=name]').value() + '</b> ?', function (result) {
                if (result) {
                    bootbox.confirm('<table><tr><td style="vertical-align:middle;font-size:2em;padding-right:10px"><span class="label label-warning"><i class="fa fa-warning"</i>' +
                            '</span></td><td style="vertical-align:middle">' + '{{Tous les équipements associés au broker vont être supprimés}}' +
                            '...<br><b>' + '{{Êtes vous sûr ?}}' + '</b></td></tr></table>', function (result) {
                        if (result) {
                            jeedom.eqLogic.remove({
                                type: eqType,
                                id: $('.eqLogicAttr[data-l1key=id]').value(),
                                error: function (error) {
                                    $('#div_alert').showAlert({message: error.message, level: 'danger'});
                                },
                                success: function () {
                                    var url = initPluginUrl();
                                    modifyWithoutSave = false;
                                    url += '&id=' + data.id + '&removeSuccessFull=1';
                                    loadPage(url);
                                }
                            });                        
                        }
                    });
                }
            });
        } else {
            $('#div_alert').showAlert({message: '{{Veuillez d\'abord sélectionner un}} ' + eqType, level: 'danger'});
        }
    }
    else {
        $(this).click();
    }
});


/**
 * printEqLogic callback called by plugin.template before calling addCmdToTable.
 *   . Reorder commands if the JSON view is active
 *   . Show the fields depending on the type (broker or equipment)
 */
function printEqLogic(_eqLogic) {

    // Principle of the ordering algorithm is to associate an ordering string to
    // each command, and then ordering into alphabetical order

    // Encode the given number in base 36, on 3 caracters width
    function toString36(_n) {
        var ret = parseInt(_n).toString(36);
        if (ret.length < 3)
            ret = "0".repeat(3-ret.length) + ret;
        return ret;
    }

    // Return the ordering string of the given command
    function computeOrder(_c) {
        if (_c.sOrder != undefined)
            return _c.sOrder;
        var sParent = '';
        if (_c.configuration.jParent != undefined && _c.configuration.jParent >= 0) {
            var tmp = _eqLogic.cmd.filter(function (c) { return c.id == _c.configuration.jParent; });
            sParent = computeOrder(tmp[0]);
        }
        if (_c.configuration.jOrder == undefined || _c.configuration.jOrder < 0)
            sOrder = toString36(_c.order);
        else
            sOrder = toString36(_c.configuration.jOrder);
        return sParent + sOrder;
    }

    // JSON view button is active
    if ($('#bt_json.active').length) {

        // Initialize the counter used
        N_CMD = 1;

        // Compute the ordering string of each commands
        for (var c of _eqLogic.cmd) {
            c.sOrder = computeOrder(c);
        }

        // Sort the command array
        _eqLogic.cmd.sort(function(c1, c2) {
            if (c1.sOrder < c2.sOrder)
                return -1;
            if (c1.sOrder > c2.sOrder)
                return 1;
            return 0;
        });

        // Disable the sortable functionality and enlarge the Id column width
        $("#table_cmd").sortable('disable');
        $("#table_cmd th:first").width('120px');
    }
    else {
        // Classical view: enable the sortable functionality and adapt the Id
        // column width
        $("#table_cmd").sortable('enable');
        $("#table_cmd th:first").width('50px');
    }

    // Show UI elements depending on the type
    if (_eqLogic.configuration.type == 'broker') {
        $('.typ-std').hide();
        $('.typ-brk').show();
        $('#sel_icon_div').css("visibility", "hidden");
        $('#mqtttopic').prop('readonly', true);
        var log = 'jMQTT_' + (_eqLogic.configuration.mqttId || 'jeedom');
        $('.bt_plugin_conf_view_log').attr('data-log', log);
        $('.bt_plugin_conf_view_log').html('<i class="fa fa fa-file-text-o"></i> ' + log);
        
    } else {
        $('.typ-brk').hide();
        $('.typ-std').show();
        $('#sel_icon_div').css("visibility", "visible");
        $('#mqtttopic').prop('readonly', false);
    }
}


/**
 * addCmdToTable callback called by plugin.template: render eqLogic commands
 */
function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    if (init(_cmd.type) == 'info') {
        // FIXME: is this disabled variable usefull?
        var disabled = (init(_cmd.configuration.virtualAction) == '1') ? 'disabled' : '';

        var tr = '<tr class="cmd';
        if ($('#bt_json.active').length) {
            tr += ' treegrid-' + N_CMD;
            if (_cmd.configuration.jParent >= 0) {
                tr += ' treegrid-parent-' + $('.cmd[data-cmd_id=' + _cmd.configuration.jParent + ']').attr('class').split('treegrid-')[1]
            }
        }
        tr += '" data-cmd_id="' + init(_cmd.id) + '">';
        tr += '<td><span class="cmdAttr" data-l1key="id"></span>';

        // TRICK: For the JSON view include the "order" value in a hidden
        // element
        // so that the original/natural order is kept when saving
        if ($('#bt_json.active').length) {
            tr += '<span style="display:none;" class="cmdAttr" data-l1key="order"></span></td>'
        }
        else
            tr += '</td>'

                tr += '<td><textarea class="cmdAttr form-control input-sm" data-l1key="name" style="height:65px;" placeholder="{{Nom de l\'info}}" /></td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="info" disabled style="margin-bottom:5px;width:120px;" />';
        tr += '<span class="cmdAttr subType" subType="' + init(_cmd.subType) + '"></span>';
        tr += '</td><td>';
        tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" style="height:65px;" ' + disabled + ' placeholder="{{Topic}}" readonly=true />';
        tr += '</td><td>';
        tr += '<textarea class="form-control input-sm" data-key="value" style="height:65px;" ' + disabled + ' placeholder="{{Valeur}}" readonly=true />';
        tr += '</td><td>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="unite" placeholder="{{Unité}}"></td><td>';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isHistorized" checked/>{{Historiser}}</label></span> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="display" data-l2key="invertBinary"/>{{Inverser}}</label></span> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="parseJson"/>{{parseJson}}</label></span> ';	
        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fa fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
        tr += '<input style="width:82%;margin-bottom:2px;" class="tooltips cmdAttr form-control input-sm" data-l1key="cache" data-l2key="lifetime" placeholder="{{Lifetime cache}}" title="{{Lifetime cache}}">';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width:40%;display:inline-block;"> ';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width:40%;display:inline-block;">';
        tr += '</td></tr>';

        $('#table_cmd tbody').append(tr);
        $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
        if (isset(_cmd.type)) {
            $('#table_cmd tbody tr:last .cmdAttr[data-l1key=type]').value(init(_cmd.type));
        }
        jeedom.cmd.changeType($('#table_cmd tbody tr:last'), init(_cmd.subType));

        function refreshValue(val) {
            $('.cmd[data-cmd_id=' + _cmd.id + '] .form-control[data-key=value]').value(val);
        }

        jeedom.cmd.execute({
            id: _cmd.id,
            cache: 0,
            notify: false,
            success: function(result) {
                refreshValue(result);
        }});
        jeedom.cmd.update[_cmd.id] = function(_options) {
            refreshValue(_options.display_value);
        }
        N_CMD++;
    }

    if (init(_cmd.type) == 'action') {
        var tr = '<tr class="cmd treegrid-' + N_CMD + '" data-cmd_id="' + init(_cmd.id) + '">';
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<div class="row">';
        tr += '<div class="col-sm-4">';
        tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon" style="padding-left:5px;padding-right:5px;"><i class="fa fa-flag"></i>  Icône</a>';
        tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left:5px;"></span>';
        tr += '</div>';
        tr += '<div class="col-sm-8">';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
        tr += '</div>';
        tr += '</div>';
        tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="display:none;margin-top:5px;margin-right:10px;" title="{{Valeur par défaut de la commande}}">';
        tr += '<option value="">Aucune</option>';
        tr += '</select>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom:5px;width:120px;" />';
        tr += '<span class="cmdAttr subType" subType="' + init(_cmd.subType) + '" style=""></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="topic" style="height:65px;"' + disabled + ' placeholder="{{Topic}}"></textarea><br/>';
        tr += '</td><td>';
        tr += '<textarea class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="request" style="height:30px;" ' + disabled + ' placeholder="{{Valeur}}"></textarea>';
        tr += '<a class="btn btn-default btn-sm cursor listEquipementInfo" data-input="request" style="margin-top:5px;margin-left:5px;"><i class="fa fa-list-alt "></i> {{Rechercher équipement}}</a>';
        tr +='</select></span>';
        tr += '</td><td></td><td>';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="isVisible" checked/>{{Afficher}}</label></span><br> ';
        tr += '<span><label class="checkbox-inline"><input type="checkbox" class="cmdAttr checkbox-inline" data-l1key="configuration" data-l2key="retain"/>{{Retain}}</label></span><br> ';
        tr += '<span class="checkbox-inline">{{Qos}}: <input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="Qos" placeholder="{{Qos}}" title="{{Qos}}" style="width:50px;display:inline-block;"></span> ';
        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
        tr += '</tr>';
        
        $('#table_cmd tbody').append(tr);
        // $('#table_cmd tbody tr:last').setValues(_cmd, '.cmdAttr');
        var tr = $('#table_cmd tbody tr:last');
        jeedom.eqLogic.builSelectCmd({
            id: $(".li_eqLogic.active").attr('data-eqLogic_id'),
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
        N_CMD++;
    }

    // If JSON view is active, build the tree
    if ($('#bt_json.active').length) {
        $('.tree').treegrid({
            expanderExpandedClass: 'glyphicon glyphicon-minus',
            expanderCollapsedClass: 'glyphicon glyphicon-plus'
        });
    }
}

/**
 * Management of the display when an information command is added
 * Triggerred when the plugin core send a jMQTT::cmdAdded event
 * @param _event string event name
 * @param _options['eqlogic_name'] string name of the eqLogic command is added to
 * @param _options['eqlogic_id'] int id of the eqLogic command is added to
 * @param _options['cmd_name'] string name of the new command
 */
$('body').off('jMQTT::cmdAdded').on('jMQTT::cmdAdded', function(_event,_options) {

    if ($('#div_newCmdMsg.alert').length == 0)
        var msg = '{{La commande}} <b>' + _options['cmd_name'] + '</b> {{a été ajoutée à l\'équipement}}' +
        ' <b>' + _options['eqlogic_name'] + '</b>.';
    else
        var msg = '{{Plusieurs commandes ont été ajoutée à l\'équipement}} <b>' + _options['eqlogic_name'] + '</b>.';

    // If the page is being modified or another equipment is being consulted or a dialog box is shown: display a simple alert message
    if (modifyWithoutSave || $('.li_eqLogic.active').attr('data-eqLogic_id') != _options['eqlogic_id'] ||
            $('div[role="dialog"]').filter(':visible').length != 0) {
        $('#div_newCmdMsg').showAlert({message: msg, level: 'warning'});
    }
    // Otherwise: display an alert message and reload the page
    else {
        $('#div_newCmdMsg').showAlert({
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

//Configure the display according to the given mode
//If given mode is not provided, use the bt_changeIncludeMode data-mode attribute value
function configureIncludeModeDisplay(brkId, mode) {
    if (mode == 1) {
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']:not(.card)').removeClass('btn-default').addClass('btn-success');
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').attr('data-mode', 1);
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+'].card span center').text('{{Arrêter l\'inclusion}}');
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']:not(.card)').html('<i class="fa fa-sign-in fa-rotate-90"></i> {{Arreter inclusion}}');
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').addClass('include');
        $('#div_inclusionModeMsg').showAlert({message: '{{Mode inclusion automatique pendant 2 à 3min. Cliquez sur le bouton pour forcer la sortie de ce mode avant.}}', level: 'warning'});
    } else {
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']:not(.card)').addClass('btn-default').removeClass('btn-success btn-danger');
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').attr('data-mode', 0);
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']:not(.card)').html('<i class="fa fa-sign-in fa-rotate-90"></i> {{Mode inclusion}}');
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+'].card span center').text('{{Mode inclusion}}');
        $('.eqLogicAction[data-action=changeIncludeMode][brkId='+brkId+']').removeClass('include');
        $('#div_inclusionModeMsg').hideAlert();
    }
}

//Manage button clicks
$('.eqLogicAction[data-action=changeIncludeMode]').on('click', function () {
    var el = $(this);
    console.log(el.attr('data-mode'));

    // Invert the button display and show the alert message
    if (el.attr('data-mode') == 1) {
        configureIncludeModeDisplay(el.attr('brkId'),0);
    }
    else {
        configureIncludeModeDisplay(el.attr('brkId'),1);
    }

    // Ajax call to inform the plugin core of the change
    $.ajax({
        type: "POST", 
        url: "plugins/jMQTT/core/ajax/jMQTT.ajax.php", 
        data: {
            action: "changeIncludeMode",
            mode: el.attr('data-mode'),
            id: el.attr('brkId')
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) { 
            if (data.state != 'ok') {
                $('#div_alert').showAlert({message: data.result, level: 'danger'});
                return;
            }
        }
    });
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
    if (modifyWithoutSave || $('.li_eqLogic.active').attr('data-eqLogic_id') != undefined ||
            $('div[role="dialog"]').filter(':visible').length != 0) {
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
