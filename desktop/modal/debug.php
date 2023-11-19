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

function panelCreator($title, $type, $icon, $builder) {
    echo '            <div class="panel panel-'.$type.'">';
    echo '                <div class="panel-heading"><h3 class="panel-title"><i class="'.$icon.'"></i> '.$title;
    echo '                <a class="btn btn-info btn-show-hide btn-xs btn-success pull-right" builder="'.$builder.'" style="top:-2px!important">';
    echo '                <i class="fas fa-search-plus"></i> {{Afficher}} </a></h3></div><div class="panel-body hidden"></div>';
    echo '            </div>';
}
?>
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
                if (typeof _params.error === 'function') {
                    _params.error(data.result);
                } else {
                    $.fn.showAlert({message: data.result, level: 'danger'});
                }
            }
            else {
                if (typeof _params.success === 'function') {
                    _params.success(data.result);
                }
            }
        }
    });
}

function builder_cfgCache(_div, _action, _buttons) {
    callDebugAjax({
        data: { action: _action },
        error: function(error) { $.fn.showAlert({message: error, level: 'danger'}) },
        success: function(_data) {
            var res = '<table class="table table-bordered" style="table-layout:fixed;width:100%;">';
            res += '<thead><tr><th style="width:180px">{{Clé}}</th><th>{{Valeur (encodée en Json)}}</th>';
            res += '<th style="width:85px;text-align:center">';
            if (_data[0] && !_data[0].id)
                res += '<a class="btn btn-success btn-xs pull-right add" style="top:0px!important;"><i class="fas fa-check-circle icon-white"></i> {{Ajouter}}</a>';
            res += '</th></tr></thead><tbody>';
            for (var group of _data) {
                if (group.header) {
                    if (group.id) {
                        res += '<tr eqId="' + group.id + '"><td colspan="2" style="font-weight:bolder;">' + group.header + '</td>';
                        res += '<td><a class="btn btn-success btn-xs pull-right add" style="top:0px!important;">';
                        res += '<i class="fas fa-check-circle icon-white"></i> {{Ajouter}}</a></td></tr>';
                    } else {
                        res += '<tr><td colspan="3" style="font-weight:bolder;">' + group.header + '</td></tr>';
                    }
                }
                for (var d of group.data) {
                    res += (group.id) ? '<tr eqId="' + group.id + '">' : '<tr>';
                    res += '<td class="key">' + d.key + '</td><td><pre class="val">' + JSON.stringify(d.value) + '</pre></td>';
                    res += '<td style="text-align:center"><a class="btn btn-warning btn-sm edit"><i class="fas fa-pen"></i></a>&nbsp;';
                    res += '<a class="btn btn-danger btn-sm del"><i class="fas fa-trash"></i></a></td></tr>';
                }
            }
            res += '</tbody></table>';
            _div.html(res);
            if(typeof _buttons === 'function')
                _buttons(_div);
        }
    });
}

function configIntButtons(div) {
    div.off('click', 'a.add').on('click', 'a.add', function() {
        bootbox.confirm({
            title: '{{Ajouter un paramètre de configuration interne}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" autofill="off" type="text" id="debugKey"><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configSetInternal",
                        key : $("#debugKey").val(),
                        val: $("#debugVal").val()
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config interne ajouté.}}', level: 'success'});
                        var row = div.find('tbody').prepend('<tr />').children('tr:first');
                        row.append('<td class="key">'+$("#debugKey").val()+'</td>');
                        row.append('<td><pre class="val">'+$("#debugVal").val()+'</pre></td>');
                        row.append('<td style="text-align:center"><a class="btn btn-warning btn-sm edit"><i class="fas fa-pen"></i></a>&nbsp;<a class="btn btn-danger btn-sm del"><i class="fas fa-trash"></i></a></td>');
                    }
                });
                }
            }
        });
    });

    div.off('click', 'a.edit').on('click', 'a.edit', function() {
        var tr = $(this).closest('tr');
        var debugKey = tr.find('.key').text();
        bootbox.confirm({
            title: '{{Modifier le paramètre de configuration interne}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configSetInternal",
                        key : debugKey,
                        val: $("#debugVal").val()
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config interne modifié.}}', level: 'success'});
                        tr.find('.val').text($("#debugVal").val());
                    }
                });
                }
            }
        });
    });

    div.off('click', 'a.del').on('click', 'a.del', function() {
        var tr = $(this).closest('tr');
        var debugKey = tr.find('.key').text();
        bootbox.confirm({
            title: '{{Supprimer le paramètre de configuration interne}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" disabled readonly=true>'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configDelInternal",
                        key : debugKey
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config interne supprimé.}}', level: 'success'});
                        tr.remove();
                    }
                });
                }
            }
        });
    });
}
function configBrkEqButtons(div) {
    div.off('click', 'a.add').on('click', 'a.add', function() {
        var tr = $(this).closest('tr');
        var debugId = tr.attr('eqId');
        bootbox.confirm({
            title: '{{Ajouter un paramètre de configuration}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" autofill="off" type="text" id="debugKey"><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configSetBrkAndEqpt",
                        id : debugId,
                        key : $("#debugKey").val(),
                        val: $("#debugVal").val()
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config ajouté.}}', level: 'success'});
                        var row = '<tr eqId=' + debugId + '><td class="key">'+$("#debugKey").val()+'</td>';
                        row += '<td><pre class="val">'+$("#debugVal").val()+'</pre></td>';
                        row += '<td style="text-align:center"><a class="btn btn-warning btn-sm edit"><i class="fas fa-pen"></i></a>&nbsp;';
                        row += '<a class="btn btn-danger btn-sm del"><i class="fas fa-trash"></i></a></td></tr>';
                        tr.after(row);
                    }
                });
                }
            }
        });
    });

    div.off('click', 'a.edit').on('click', 'a.edit', function() {
        var tr = $(this).closest('tr');
        var debugId = tr.attr('eqId');
        var debugKey = tr.find('.key').text();
        bootbox.confirm({
            title: '{{Modifier le paramètre de configuration}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configSetBrkAndEqpt",
                        id : debugId,
                        key : debugKey,
                        val: $("#debugVal").val()
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config modifié.}}', level: 'success'});
                        tr.find('.val').text($("#debugVal").val());
                    }
                });
                }
            }
        });
    });

    div.off('click', 'a.del').on('click', 'a.del', function() {
        var tr = $(this).closest('tr');
        var debugId = tr.attr('eqId');
        var debugKey = tr.find('.key').text();
        bootbox.confirm({
            title: '{{Supprimer le paramètre de configuration}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" disabled readonly=true>'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configDelBrkAndEqpt",
                        id : debugId,
                        key : debugKey
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config supprimé.}}', level: 'success'});
                        tr.remove();
                    }
                });
                }
            }
        });
    });
}
function configCmdButtons(div) {
    div.off('click', 'a.add').on('click', 'a.add', function() {
        var tr = $(this).closest('tr');
        var debugId = tr.attr('eqId');
        bootbox.confirm({
            title: '{{Ajouter un paramètre de configuration}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" autofill="off" type="text" id="debugKey"><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configSetCommands",
                        id : debugId,
                        key : $("#debugKey").val(),
                        val: $("#debugVal").val()
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config ajouté.}}', level: 'success'});
                        var row = '<tr eqId=' + debugId + '><td class="key">'+$("#debugKey").val()+'</td>';
                        row += '<td><pre class="val">'+$("#debugVal").val()+'</pre></td>';
                        row += '<td style="text-align:center"><a class="btn btn-warning btn-sm edit"><i class="fas fa-pen"></i></a>&nbsp;';
                        row += '<a class="btn btn-danger btn-sm del"><i class="fas fa-trash"></i></a></td></tr>';
                        tr.after(row);
                    }
                });
                }
            }
        });
    });

    div.off('click', 'a.edit').on('click', 'a.edit', function() {
        var tr = $(this).closest('tr');
        var debugId = tr.attr('eqId');
        var debugKey = tr.find('.key').text();
        bootbox.confirm({
            title: '{{Modifier le paramètre de configuration}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configSetCommands",
                        id : debugId,
                        key : debugKey,
                        val: $("#debugVal").val()
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config modifié.}}', level: 'success'});
                        tr.find('.val').text($("#debugVal").val());
                    }
                });
                }
            }
        });
    });

    div.off('click', 'a.del').on('click', 'a.del', function() {
        var tr = $(this).closest('tr');
        var debugId = tr.attr('eqId');
        var debugKey = tr.find('.key').text();
        bootbox.confirm({
            title: '{{Supprimer le paramètre de configuration}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" disabled readonly=true>'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "configDelCommands",
                        id : debugId,
                        key : debugKey
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de config supprimé.}}', level: 'success'});
                        tr.remove();
                    }
                });
                }
            }
        });
    });
}
function cacheButtons(div) {
    div.off('click', 'a.add').on('click', 'a.add', function() {
        bootbox.confirm({
            title: '{{Ajouter un paramètre au cache}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" autofill="off" type="text" id="debugKey"><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result) {
                if (result) {
                callDebugAjax({
                    data: {
                        action: "cacheSet",
                        key : $("#debugKey").val(),
                        val: $("#debugVal").val()
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre de cache ajouté.}}', level: 'success'});
                        var row = div.find('tbody').prepend('<tr />').children('tr:first');
                        row.append('<td class="key">'+$("#debugKey").val()+'</td>');
                        row.append('<td><pre class="val">'+$("#debugVal").val()+'</pre></td>');
                        row.append('<td style="text-align:center"><a class="btn btn-warning btn-sm edit"><i class="fas fa-pen"></i></a>&nbsp;<a class="btn btn-danger btn-sm del"><i class="fas fa-trash"></i></a></td>');
                    }
                });
                }
            }
        });
    });

    div.off('click', 'a.edit').on('click', 'a.edit', function() {
        var tr = $(this).closest('tr');
        var debugKey = tr.find('.key').text();
        bootbox.confirm({
            title: '{{Modifier le paramètre du cache}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" id="debugVal">'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result){
                if (result) {
                callDebugAjax({
                    data: {
                        action: "cacheSet",
                        key : debugKey,
                        val: $("#debugVal").val()
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre du cache modifié.}}', level: 'success'});
                        tr.find('.val').text($("#debugVal").val());
                    }
                });
                }
            }
        });
    });

    div.off('click', 'a.del').on('click', 'a.del', function() {
        var tr = $(this).closest('tr');
        var debugKey = tr.find('.key').text();
        bootbox.confirm({
            title: '{{Supprimer le paramètre du cache}}',
            message: '<label class="control-label">{{Clé :}} </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">{{Valeur (encodée en Json) :}} </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" disabled readonly=true>'+$(this).closest('tr').find('.val').text()+'</textarea><br/><br/>',
            callback: function(result){
                if (result) {
                callDebugAjax({
                    data: {
                        action: "cacheDel",
                        key : debugKey
                    },
                    error: function(error) {
                        $.fn.showAlert({message: error, level: 'danger'})
                    },
                    success: function(data) {
                        $.fn.showAlert({message: '{{Paramètre du cache supprimé.}}', level: 'success'});
                        tr.remove();
                    }
                });
                }
            }
        });
    });
}

function builder_daemon(div) {
    var res = '<form class="form-horizontal"><fieldset>';
    // Send to Daemon
    res += '<legend><i class="fas fa-upload"></i> {{Simuler un évènement envoyé au Démon par Jeedom (clé API envoyée en auto)}}</legend><div class="form-group"><div class="col-sm-10">';
    res += '<textarea class="bootbox-input bootbox-input-text form-control toDaemon" style="min-height:65px;">';
    res += '{"cmd": "newMqttClient", "id": "", "hostname": "", "port": "", "mqttId": "", "mqttIdValue": "", "lwt": "", "lwtTopic": "", "lwtOnline": "", "lwtOffline": "", "username": "", "password": "", "paholog": "", "tls": "", "tlsinsecure": "", "tlscafile": "", "tlsclicertfile": "", "tlsclikeyfile": ""}\n';
    res += '{"cmd": "removeMqttClient", "id": ""}\n';
    res += '{"cmd": "subscribeTopic", "id": "", "topic": "", "qos": ""}\n';
    res += '{"cmd": "unsubscribeTopic", "id": "", "topic": ""}\n';
    res += '{"cmd": "messageOut", "id": "", "topic": "", "payload": "", "qos": "", "retain": ""}\n';
    res += '{"cmd": "hb", "id": ""}\n';
    res += '{"cmd": "loglevel", "id": "", "level": ""}';
    res += '\n';
    res += '{"cmd": "", "id": "", "hostname": "", "port": "", "mqttId": "", "mqttIdValue": "", "lwt": "", "lwtTopic": "", "lwtOnline": "", "lwtOffline": "", "username": "", "password": "", "paholog": "", "tls": "", "tlsinsecure": "", "tlscafile": "", "tlsclicertfile": "", "tlsclikeyfile": "", "payload": "", "qos": "", "retain": "", "topic": ""}\n';

    res += '</textarea></div><div class="col-sm-2"><a class="btn btn-success btn-xs pull-right toDaemon" style="top:0px!important;">';
    res += '<i class="fas fa-check-circle icon-white"></i> {{Envoyer}}</a></div></div>';
    // Send to Jeedom
    res += '<legend><i class="fas fa-download"></i> {{Simuler un évènement reçu du Démon par Jeedom (clé API envoyée en auto)}}</legend><div class="form-group"><div class="col-sm-10">';
    res += '<textarea class="bootbox-input bootbox-input-text form-control toJeedom" style="min-height:65px;">';

    res += '[{"cmd":"messageIn", "id":string, "topic":string, "payload":string, "qos":string, "retain":string}]\n';
    res += '[{"cmd":"brokerUp", "id":string}]\n';
    res += '[{"cmd":"brokerDown"}]\n';
    res += '[{"cmd":"daemonUp"}]\n';
    res += '[{"cmd":"daemonDown"}]\n';
    res += '[{"cmd":"hb"}]';

    res += '</textarea></div><div class="col-sm-2"><a class="btn btn-success btn-xs pull-right toJeedom" style="top:0px!important;">';
    res += '<i class="fas fa-check-circle icon-white"></i> {{Envoyer}}</a></div></div><br/>';

    res += '</fieldset></form>';
    div.html(res);

    div.off('click', 'a.toDaemon').on('click', 'a.toDaemon', function() {
        callDebugAjax({
            data: {
                action: "sendToDaemon",
                data : $(this).closest('form').find('textarea.toDaemon').value()
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Evènement envoyé au Démon', level: 'success'});
            }
        });
    });
    div.off('click', 'a.toJeedom').on('click', 'a.toJeedom', function() {
        callDebugAjax({
            data: {
                action: "sendToJeedom",
                data : $(this).closest('form').find('textarea.toJeedom').value()
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Evènement envoyé au Démon', level: 'success'});
            }
        });
    });
}

function builder_configInt(div)  { builder_cfgCache(div, "configGetInternal",       configIntButtons); }
function builder_configBrk(div)  { builder_cfgCache(div, "configGetBrokers",        configBrkEqButtons); }
function builder_configEqp(div)  { builder_cfgCache(div, "configGetEquipments",     configBrkEqButtons); }
function builder_configCmdI(div) { builder_cfgCache(div, "configGetCommandsInfo",   configCmdButtons); }
function builder_configCmdA(div) { builder_cfgCache(div, "configGetCommandsAction", configCmdButtons); }

function builder_actions(div) {
    var res = /*'<legend><i class="fas fa-hands-wash "></i> {{Nettoyage}}</legend>'*/'<div class="form-group">';
    res += '<div class="col-sm-5"><a class="btn btn-warning btn-xs depCheck" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-check-circle icon-white"></i> {{Forcer la revérification des dépendances}}</a></div>';
    res += '<div class="col-sm-4"><a class="btn btn-danger btn-xs venvDelete" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-trash"></i> {{Supprimer déps Python (venv)}}</a></div>';
    res += '<div class="col-sm-3"><a class="btn btn-danger btn-xs depDelete" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-trash"></i> {{Supprimer déps PHP}}</a></div>';

    res += '<div class="col-sm-5"><a class="btn btn-danger btn-xs dynContentDelete" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-trash"></i> {{Supprimer les contenus dynamiques}}</a></div>';
    res += '<div class="col-sm-4"><a class="btn btn-danger btn-xs pidFileDelete" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-trash"></i> {{Supprimer le fichier PID}}</a></div>';
    res += '<div class="col-sm-3"><a class="btn btn-danger btn-xs hbStop" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-stop"></i> {{Arrêter les heatbeat}}</a></div>';

    res += '<div class="col-sm-5"><a class="btn btn-info btn-xs threadDump" style="width:100%;text-align:left;">';
    res += '<i class="icon kiko-zoom"></i> {{Demander au démon un "Thread Dump"}}</a></div>';
    res += '<div class="col-sm-4"><a class="btn btn-warning btn-xs reInstall" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-bicycle"></i> {{Réinstaller jMQTT}}</a></div>';
    res += '<div class="col-sm-3"><a class="btn btn-info btn-xs statsSend" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-satellite"></i> {{Envoyer les stats}}</a></div>';

    res += '<div class="col-sm-5"><a class="btn btn-danger btn-xs listenersRemove" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-assistive-listening-systems"></i> {{Supprimer tous les listeners}}</a></div>';
    res += '<div class="col-sm-4"><a class="btn btn-success btn-xs listenersCreate" style="width:100%;text-align:left;">';
    res += '<i class="fas fa-assistive-listening-systems"></i> {{Re-créer les listeners}}</a></div>';
    res += '<div class="col-sm-3"><a class="btn btn-info btn-xs logVerbose" style="width:100%;text-align:left;">';
    res += '<i class="far fa-file"></i> {{Logs en VERBOSE}}</a></div>';

// - List active Python daemon(s) PID (and allow to kill some/all of them)
    // res += '<div class="col-sm-12">&nbsp;</div></div><legend><i class="fas fa-university"></i> {{Démon(s) actifs}}</legend>';
    // res += '<div class="col-sm-12" id="actionsDeamons"></div>';

    res += '<div class="col-sm-12">&nbsp;</div></div>';
    div.html(res);

/*
    var _div = $('#actionsDeamons');
    callDebugAjax({
        data: { action: 'configGetInternal' },
        error: function(error) { $.fn.showAlert({message: error, level: 'danger'}) },
        success: function(_data) {
            var res = '<table class="table table-bordered" style="table-layout:fixed;width:100%;">';
            res += '<thead><tr><th style="width:180px">PID</th><th>{{Port}}</th>';
            res += '<th style="width:85px;text-align:center"><a class="btn btn-danger btn-xs pull-right killAll" style="top:0px!important;">';
            res += '<i class="fas fa-check-circle icon-white"></i> {{Kill All}}</a></th>';
            res += '</tr></thead><tbody>';
            for (var d of _data) {
                res += '<tr><td class="key">' + d.pid + '</td><td><pre class="val">' + d.port + '</pre></td>';
                // IF d.selected THEN add a tick -> <i class="fas fa-check-circle icon-white"></i>
                res += '<td style="text-align:center"><a class="btn btn-danger btn-sm del"><i class="fas fa-trash"></i></a></td></tr>';
            }
            res += '</tbody></table>';
            _div.html(res);
            if(typeof _buttons === 'function')
                _buttons(_div);
        }
    });
*/


    div.off('click', 'a.depCheck').on('click', 'a.depCheck', function() {
        callDebugAjax({
            data: {
                action: "depCheck"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Revérification des dépendances lancée', level: 'success'});
            }
        });
    });
    div.off('click', 'a.depDelete').on('click', 'a.depDelete', function() {
        callDebugAjax({
            data: {
                action: "depDelete"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Dépendances PHP supprimées', level: 'success'});
            }
        });
    });
    div.off('click', 'a.venvDelete').on('click', 'a.venvDelete', function() {
        callDebugAjax({
            data: {
                action: "venvDelete"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Dépendances Python (venv) supprimées', level: 'success'});
            }
        });
    });
    div.off('click', 'a.dynContentDelete').on('click', 'a.dynContentDelete', function() {
        callDebugAjax({
            data: {
                action: "dynContentDelete"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Contenus dynamiques supprimés', level: 'success'});
            }
        });
    });
    div.off('click', 'a.pidFileDelete').on('click', 'a.pidFileDelete', function() {
        callDebugAjax({
            data: {
                action: "pidFileDelete"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Fichier PID supprimé', level: 'success'});
            }
        });
    });
    div.off('click', 'a.hbStop').on('click', 'a.hbStop', function() {
        callDebugAjax({
            data: {
                action: "hbStop"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Heartbeat arrêtés', level: 'success'});
            }
        });
    });
    div.off('click', 'a.threadDump').on('click', 'a.threadDump', function() {
        callDebugAjax({
            data: {
                action: "threadDump"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Demande de dump des threads au Démon envoyée', level: 'success'});
            }
        });
    });
    div.off('click', 'a.reInstall').on('click', 'a.reInstall', function() {
        callDebugAjax({
            data: {
                action: "reInstall"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Réinstallation des dépendances lancée', level: 'success'});
            }
        });
    });
    div.off('click', 'a.statsSend').on('click', 'a.statsSend', function() {
        callDebugAjax({
            data: {
                action: "statsSend"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Statistiques envoyées au serveur', level: 'success'});
            }
        });
    });
    div.off('click', 'a.listenersRemove').on('click', 'a.listenersRemove', function() {
        callDebugAjax({
            data: {
                action: "listenersRemove"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Listeners supprimés', level: 'success'});
            }
        });
    });
    div.off('click', 'a.listenersCreate').on('click', 'a.listenersCreate', function() {
        callDebugAjax({
            data: {
                action: "listenersCreate"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Listeners re-créés', level: 'success'});
            }
        });
    });
    div.off('click', 'a.logVerbose').on('click', 'a.logVerbose', function() {
        callDebugAjax({
            data: {
                action: "logVerbose"
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Logs du démon à présent en niveau VERBOSE', level: 'success'});
            }
        });
    });

}

function builder_cacheInt(div)   { builder_cfgCache(div, "cacheGetInternal",        cacheButtons); }
function builder_cacheBrk(div)   { builder_cfgCache(div, "cacheGetBrokers",         cacheButtons); }
function builder_cacheEqp(div)   { builder_cfgCache(div, "cacheGetEquipments",      cacheButtons); }
function builder_cacheCmdI(div)  { builder_cfgCache(div, "cacheGetCommandsInfo",    cacheButtons); }
function builder_cacheCmdA(div)  { builder_cfgCache(div, "cacheGetCommandsAction",  cacheButtons); }
    </script>
    <div class="row">
        <style>td.key { line-break: anywhere; }</style>
        <div class="col-md-6 col-sm-12"><!-- General status of Jeedom -->
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
<?php
// Simulate send to daemon
panelCreator('{{Simuler une communication avec le Démon}}', 'danger', 'fas fa-exchange-alt', 'builder_daemon');
// Config values
panelCreator('{{Valeurs de config du Démon}}',             'primary', 'fas fa-wrench', 'builder_configInt');
panelCreator('{{Valeurs de config des Brokers}}',          'primary', 'fas fa-wrench', 'builder_configBrk');
panelCreator('{{Valeurs de config des Equipements}}',      'primary', 'fas fa-wrench', 'builder_configEqp');
panelCreator('{{Valeurs de config des Commandes Info}}',   'primary', 'fas fa-wrench', 'builder_configCmdI');
panelCreator('{{Valeurs de config des Commandes Action}}', 'primary', 'fas fa-wrench', 'builder_configCmdA');
?>
        </div>
        <div class="col-md-6 col-sm-12"><!-- General status of jMQTT -->
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
<?php
// Simulate dangerous actions on jMQTT
panelCreator('{{Actions sur jMQTT}}',                     'danger',  'fas fa-radiation-alt', 'builder_actions');
// Cache values
panelCreator('{{Valeurs du cache du Démon}}',             'primary', 'fas fa-book',   'builder_cacheInt');
panelCreator('{{Valeurs du cache des Brokers}}',          'primary', 'fas fa-book',   'builder_cacheBrk');
panelCreator('{{Valeurs du cache des Equipements}}',      'primary', 'fas fa-book',   'builder_cacheEqp');
panelCreator('{{Valeurs du cache des Commandes Info}}',   'primary', 'fas fa-book',   'builder_cacheCmdI');
panelCreator('{{Valeurs du cache des Commandes Action}}', 'primary', 'fas fa-book',   'builder_cacheCmdA');
?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6 col-sm-12">
<?php

?>
        </div><div class="col-md-6 col-sm-12">
<?php

?>
        </div>
    </div>

    <script>
// Function to hide, show and build sections content on the fly
$('a.btn.btn-info.btn-show-hide').on('click', function () {
    var div = $(this).closest('div.panel').find('div.panel-body');
    if ($(this).hasClass('btn-warning')) {
        $(this).removeClass('btn-warning').addClass('btn-success').html('<i class="fas fa-search-plus"></i> {{Afficher}}');
        div.addClass('hidden');
        if ($(this).hasAttr('builder'))
            div.empty();
    } else {
        $(this).addClass('btn-warning').removeClass('btn-success').html('<i class="fas fa-search-minus"></i> {{Masquer}}');
        div.removeClass('hidden');
        if ($(this).hasAttr('builder')) {
            var builder = window[$(this).attr('builder')];
            if(typeof builder === 'function')
                builder(div);
        }
    }

});
    </script>
