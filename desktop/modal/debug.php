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
    throw new Exception('401 - Unauthorized access');
}

require_once __DIR__ . '/../../core/class/jMQTT.class.php';

function panelCreator($title, $type, $icon, $builder) {
    echo '<div class="panel panel-'.$type.'">';
    echo '<div class="panel-heading rounded"><h3 class="panel-title"><i class="'.$icon.'"></i> '.$title;
    echo '<a class="btn btn-info btn-show-hide btn-xs pull-right" builder="'.$builder.'" style="top:-2px!important">';
    echo '<i class="fas fa-search-plus"></i> Show </a></h3></div><div class="panel-body hidden"></div>';
    echo "</div>\n";
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

// Toggle spinner icon on button click
function debugToggleIco(_this) {
    var h = _this.find('i.fas:hidden');
    var v = _this.find('i.fas:visible');
    v.hide();
    h.show();
}

function builder_cfgCache(_div, _action, _buttons) {
    callDebugAjax({
        data: { action: _action },
        error: function(error) { $.fn.showAlert({message: error, level: 'danger'}) },
        success: function(_data) {
            var res = '<table class="table table-bordered" style="table-layout:fixed;width:100%;">';
            res += '<thead><tr><th style="width:180px">Key</th><th>Value (Json encoded)</th>';
            res += '<th style="width:85px;text-align:center">';
            if (_data[0] && !_data[0].id)
                res += '<a class="btn btn-success btn-xs pull-right add" style="top:0px!important;"><i class="fas fa-check-circle icon-white"></i> Add</a>';
            res += '</th></tr></thead><tbody>';
            for (var group of _data) {
                if (group.header) {
                    if (group.id) {
                        res += '<tr eqId="' + group.id + '"><td colspan="2" style="font-weight:bolder;">' + group.header + '</td>';
                        res += '<td><a class="btn btn-success btn-xs pull-right add" style="top:0px!important;">';
                        res += '<i class="fas fa-check-circle icon-white"></i> Add</a></td></tr>';
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
            if (typeof _buttons === 'function')
                _buttons(_div);
        }
    });
}

function configIntButtons(div) {
    div.off('click', 'a.add').on('click', 'a.add', function() {
        bootbox.confirm({
            title: 'Add an internal configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" autofill="off" type="text" id="debugKey"><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Internal config parameter added.', level: 'success'});
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
            title: 'Edit internal configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Internal config parameter modified.', level: 'success'});
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
            title: 'Delete internal configuration setting',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Internal config parameter deleted.', level: 'success'});
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
            title: 'Add a configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" autofill="off" type="text" id="debugKey"><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Config parameter added.', level: 'success'});
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
            title: 'Edit configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Config parameter modified.', level: 'success'});
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
            title: 'Delete configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Config parameter deleted.', level: 'success'});
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
            title: 'Add a configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" autofill="off" type="text" id="debugKey"><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Config parameter added.', level: 'success'});
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
            title: 'Edit configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Config parameter modified.', level: 'success'});
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
            title: 'Delete configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Config parameter deleted.', level: 'success'});
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
            title: 'Add a parameter to the cache',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="off" autofill="off" type="text" id="debugKey"><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Cache parameter added.', level: 'success'});
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
            title: 'Edit cache parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\''+debugKey+'\'><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
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
                        $.fn.showAlert({message: 'Cache parameter modified.', level: 'success'});
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
            title: 'Delete cache value',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" disabled type="text" value=\'' + debugKey + '\'><br/><br/>'
                    + '<label class="control-label">Value (Json encoded): </label> '
                    + '<textarea class="bootbox-input bootbox-input-text form-control" style="min-height:65px;" disabled readonly=true>'
                    + $(this).closest('tr').find('.val').text() + '</textarea><br/><br/>',
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
                        $.fn.showAlert({message: 'Cache parameter deleted.', level: 'success'});
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
    res += '<legend><i class="fas fa-upload"></i> Simulate an event sent to Daemon by Jeedom (API key sent automatically)</legend><div class="form-group"><div class="col-sm-10">';
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
    res += '<i class="fas fa-check-circle icon-white"></i> Send</a></div></div>';
    // Send to Jeedom
    res += '<legend><i class="fas fa-download"></i> Simulate an event from Daemon (API key sent automatically)</legend><div class="form-group"><div class="col-sm-10">';
    res += '<textarea class="bootbox-input bootbox-input-text form-control toJeedom" style="min-height:65px;">';

    res += '[{"cmd":"messageIn", "id":string, "topic":string, "payload":string, "qos":string, "retain":string}]\n';
    res += '[{"cmd":"brokerUp", "id":string}]\n';
    res += '[{"cmd":"brokerDown"}]\n';
    res += '[{"cmd":"daemonUp"}]\n';
    res += '[{"cmd":"daemonDown"}]\n';
    res += '[{"cmd":"hb"}]';

    res += '</textarea></div><div class="col-sm-2"><a class="btn btn-success btn-sm pull-right toJeedom" style="top:0px!important;">';
    res += '<i class="fas fa-check-circle icon-white"></i> Send</a></div></div><br/>';

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
                $.fn.showAlert({message: 'Event sent to Daemon', level: 'success'});
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
                $.fn.showAlert({message: 'Event sent to Jeedon callback', level: 'success'});
            }
        });
    });
}

function builder_configInt(div)  { builder_cfgCache(div, "configGetInternal",       configIntButtons); }
function builder_configBrk(div)  { builder_cfgCache(div, "configGetBrokers",        configBrkEqButtons); }
function builder_configEqp(div)  { builder_cfgCache(div, "configGetEquipments",     configBrkEqButtons); }
function builder_configCmdI(div) { builder_cfgCache(div, "configGetCommandsInfo",   configCmdButtons); }
function builder_configCmdA(div) { builder_cfgCache(div, "configGetCommandsAction", configCmdButtons); }

function add_action_event(_div, _action, _level, _icon, _msg) {
    let elt = '<div class="col-sm-6">';
    elt += '<a class="btn btn-' + _level + ' btn-xs ' + _action + '" style="width:100%;text-align:left;">';
    elt += '<i class="' + _icon + ' center" style="width:15px"></i> ' + _msg + '</a></div>';
    _div.append(elt);
    _div.off('click', 'a.' + _action).on('click', 'a.' + _action, function() {
        callDebugAjax({
            data: {
                action: _action
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                if (!data) data = 'Done';
                $.fn.showAlert({message: _msg + ' -> ' + data, level: 'success'});
            }
        });
    });
}

function builder_actions(_root_div) {
    _root_div.html('');

    _root_div.append('<legend style="margin-bottom:2px!important"><i class="mdi-harddisk"></i> Installation and files</legend>');
    let div = $('<div class="form-group">');
    add_action_event(div, 'depCheck',         'success', 'fas fa-check-circle icon-white',     'Force dependencies to be rechecked');
    add_action_event(div, 'reInstall',        'warning', 'fas fa-bicycle',                     'Reinstall jMQTT');
    add_action_event(div, 'depDelete',        'danger',  'fab fa-php',                         'Delete PHP deps');
    add_action_event(div, 'venvDelete',       'danger',  'fab fa-python',                      'Delete Python deps (venv)');
    add_action_event(div, 'dynContentDelete', 'info',    'fas fa-trash',                       'Delete dynamic content');
    div.append('<div class="col-sm-6">&nbsp;</div>'); // Alignement
    div.append('<div class="col-sm-12" style="height:15px">&nbsp;</div>'); // Spacer
    _root_div.append(div);

    _root_div.append('<legend style="margin-bottom:2px!important"><i class="kiko-heart-rate"></i> Running contents</legend>');
    div = $('<div class="form-group">');
    add_action_event(div, 'listenersRemove',  'warning', 'fas fa-assistive-listening-systems', 'Delete all listeners');
    add_action_event(div, 'listenersCreate',  'success', 'fas fa-assistive-listening-systems', 'Recreate the listeners');
    add_action_event(div, 'pidFileDelete',    'danger',  'fas fa-book-dead',                   'Delete PID file');
    add_action_event(div, 'portFileDelete',   'danger',  'fas fa-book-dead',                   'Delete PORT file');
    add_action_event(div, 'killAllSIGTERM',   'success', 'fas fa-skull',                       'KillAll jMQTTd (gracefully)');
    add_action_event(div, 'killAllSIGKILL',   'warning', 'fas fa-skull-crossbones',            'KillAll jMQTTd (forcefully)');
    add_action_event(div, 'hbStart',          'success', 'fas fa-play',                        'Start Heatbeats');
    add_action_event(div, 'hbStop',           'danger',  'fas fa-stop',                        'Stop Heatbeats');
    div.append('<div class="col-sm-12" style="height:15px">&nbsp;</div>'); // Spacer
    _root_div.append(div);

    _root_div.append('<legend style="margin-bottom:2px!important"><i class="fas fa-tools"></i> Troubleshooting</legend>');
    div = $('<div class="form-group">');
    add_action_event(div, 'printTree',        'info',    'kiko-fingerprint',                   'Ask the daemon for a "Print Tree"');
    add_action_event(div, 'threadDump',       'info',    'kiko-zoom',                          'Ask the daemon for a "Thread Dump"');
    add_action_event(div, 'logVerbose',       'info',    'far fa-file',                        'VERBOSE logs');
    add_action_event(div, 'statsSend',        'info',    'fas fa-satellite',                   'Send stats');
    div.append('<div class="col-sm-12" style="height:10px">&nbsp;</div>'); // Last spacer
    _root_div.append(div);
}

function builder_backups(_root_div) {
    let res = '';
    res += '<div class="col-lg-6 col-sm-12">';

    res += '<legend><i class="fas fa-folder-open"></i>Backup jMQTT equipment and configuration</legend>';
    res += '<div class="form-group">';
    res += '<label class="col-sm-1 control-label">&nbsp;</label>';
    res += '<div class="col-sm-5">';
    res += '<a class="btn btn-success backupJMqttStart" style="width:100%;">';
    res += '<i class="fas fa-sync fa-spin" style="display:none;"></i>';
    res += ' <i class="fas fa-save"></i> Start a backup</a>';
    res += '</div>';
    res += '<div class="col-sm-6"></div>';
    res += '</div>';

    res += '<legend><i class="fas fa-tape"></i>Available backups</legend>';
    // Backup list
    res += '<div class="form-group">';
    res += '<label class="col-sm-1 control-label">&nbsp;</label>';
    res += '<div class="col-sm-10">';
    res += '<select class="form-control" id="sel_backupJMqtt"></select>';
    res += '</div>';
    res += '<div class="col-sm-1"></div>';
    res += '</div>';

    res += '<div class="form-group">';
    res += '<label class="col-sm-1 control-label">&nbsp;</label>';
    // BT Remove backup
    res += '<div class="col-sm-5">';
    res += '<a class="btn btn-danger backupJMqttRemove" style="width:100%;"><i class="fas fa-trash"></i> Delete the backup</a>';
    res += '</div>';
    // BT Restore backup
    res += '<div class="col-sm-5">';
    res += '<a class="btn btn-warning backupJMqttRestore" style="width:100%;">';
    res += '<i class="fas fa-sync fa-spin" style="display:none;"></i>&nbsp;';
    res += '<i class="far fa-file"></i> Restore a backup <span class="danger">(BETA)</span>';
    res += '</a>';
    res += '</div>';
    res += '<div class="col-sm-1"></div>';
    res += '</div>';

    res += '<div class="form-group">';
    res += '<label class="col-sm-1 control-label">&nbsp;</label>';
    // BT Download backup
    res += '<div class="col-sm-5">';
    res += '<a class="btn btn-success backupJMqttDownload" id="bt_" style="width:100%;">';
    res += '<i class="fas fa-cloud-download-alt"></i> Download the backup</a>';
    res += '</div>';
    // BT Upload backup
    res += '<div class="col-sm-5">';
    res += '<span class="btn btn-info btn-file" style="width:100%;">';
    res += '<i class="fas fa-cloud-upload-alt"></i> Upload a backup';
    res += '<input id="bt_backupJMqttUpload" type="file" accept=".tgz" name="file"';
    res += ' data-url="plugins/jMQTT/core/ajax/jMQTT.ajax.php?action=fileupload&amp;dir=backup">';
    res += '</span>';
    res += '</div>';
    res += '<div class="col-sm-1"></div>';
    res += '</div>';

    res += '</div>';
    _root_div.html(res);

    // Init list of backups
    callDebugAjax({
        data: {
            action: "backupList"
        },
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            if (data.state == 'ok') {
                $('#sel_backupJMqtt').empty();
                for (var i in data.result) {
                    var oVal = data.result[i].name;
                    var oSize = ' (' + data.result[i].size +')';
                    $('#sel_backupJMqtt').prepend('<option selected value="' + oVal + '">' + oVal + oSize + '</option>');
                }
            } else {
                $.fn.showAlert({message: data.result, level: 'danger'});
            }
        }
    });

    // Launch jMQTT backup and wait for it to end
    _root_div.off('click', 'a.backupJMqttStart').on('click', 'a.backupJMqttStart', function() {
        var btn = $(this)
        bootbox.confirm("Are you sure you want to do a backup of jMQTT?<br/>(It will NOT be possible to cancel the operation once launched.)", function(result) {
            if (!result)
                return;
            // $('a.bt_plugin_conf_view_log[data-log=jMQTT]').click();
            debugToggleIco(btn);
            callDebugAjax({
                data: {
                    action: "backupCreate"
                },
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                    debugToggleIco(btn);
                },
                success: function(data) {
                    if (data.state == 'ok') {
                        $('#sel_backupJMqtt').empty();
                        for (var i in data.result) {
                            var oVal = data.result[i].name;
                            var oSize = ' (' + data.result[i].size +')';
                            $('#sel_backupJMqtt').prepend('<option selected value="' + oVal + '">' + oVal + oSize + '</option>');
                        }
                        $.fn.showAlert({message: 'Backup performed successfully.', level: 'success'});
                    } else {
                        $.fn.showAlert({message: data.result, level: 'danger'});
                    }
                    debugToggleIco(btn);
                }
            });
        });
    });

    // Remove selected jMQTT backup
    _root_div.off('click', 'a.backupJMqttRemove').on('click', 'a.backupJMqttRemove', function() {
        if (!$('#sel_backupJMqtt option:selected').length)
            return;
        bootbox.confirm('Are you sure you want to delete <b>' + $('#sel_backupJMqtt option:selected').text() + '</b>?', function(result) {
            if (!result)
                return;
            callDebugAjax({
                data: {
                    action: "backupRemove",
                    file: $('#sel_backupJMqtt').value()
                },
                error: function (request, status, error) {
                    handleAjaxError(request, status, error);
                },
                success: function(data) {
                    if (data.state == 'ok') {
                        $.fn.showAlert({message: 'Backup deleted.', level: 'success'});
                        $('#sel_backupJMqtt option:selected').remove();
                    } else {
                        $.fn.showAlert({message: data.result, level: 'danger'});
                    }
                }
            });
        });
    });

    // Launch jMQTT restoration and wait for it to end
    _root_div.off('click', 'a.backupJMqttRestore').on('click', 'a.backupJMqttRestore', function() {
        if (!$('#sel_backupJMqtt option:selected').length)
            return;
        var btn = $(this)
        var dialog_message = '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttnotfolder">'
        dialog_message += 'Do not restore jMQTT directory from backup</label><br/>';

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttnoteqcmd">'
        dialog_message += 'Do not restore egLogics and commands</label><br/>';

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttdodelete">'
        dialog_message += 'Delete jMQTT eqLogics and cmds created since the backup</label><br/>';

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttnotcache">'
        dialog_message += 'Do not restore previous cache (keep current cache)</label><br/>';

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttnothistory">'
        dialog_message += 'Delete recent history (keep only history from the backup)</label><br/>';

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttdologs">'
        dialog_message += 'Restore previous logs (do not keep recent logs)</label><br/>';

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttdomosquitto">'
        dialog_message += 'Restore Mosquitto configuration files</label><br/><br/>';

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttnohwcheck">';
        dialog_message += 'Do not check if this system is the same as the saved one';
        dialog_message += '&nbsp;<i class="fas fa-exclamation-triangle danger tooltips" title="Only if you know EXACTLY what you\'re doing and have an EXTERNALIZED BACKUP of Jeedom."></i>';
        dialog_message += '<sup><i class="fa fa-question-circle danger tooltips" title="Only if you know EXACTLY what you\'re doing and have an EXTERNALIZED BACKUP of Jeedom."></i></sup>';
        dialog_message += '</label><br/>';

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttverbose">'
        dialog_message += "Display more information during restoration</label><br/>";

        dialog_message += '<label class="checkbox-inline"><input type="checkbox" class="bootbox-input form-control" id="restoreJMqttapply">'
        dialog_message += '<a class="success disabled">APPLY</a> the changes on this system (otherwise restore in "Dry Run" mode)</label><br/>';

        bootbox.confirm({
            title: '<b>jMQTT backup restore settings</b>',
            message: dialog_message,
            callback: function (result){ if (result) {
                // Var recuperation MUST be done here, they don't exist after this point
                var data_to_send = {
                    action: "backupRestore",
                    file: $('#sel_backupJMqtt').value(),
                    nohwcheck: $('#restoreJMqttnohwcheck').value(),
                    notfolder: $('#restoreJMqttnotfolder').value(),
                    noteqcmd: $('#restoreJMqttnoteqcmd').value(),
                    byname: $('#restoreJMqttbyname').value(),
                    dodelete: $('#restoreJMqttdodelete').value(),
                    notcache: $('#restoreJMqttnotcache').value(),
                    nothistory: $('#restoreJMqttnothistory').value(),
                    dologs: $('#restoreJMqttdologs').value(),
                    domosquitto: $('#restoreJMqttdomosquitto').value(),
                    verbose: $('#restoreJMqttverbose').value(),
                    apply: $('#restoreJMqttapplyapply').value()
                };
                bootbox.confirm('Are you sure you want to restore <b>' + $('#sel_backupJMqtt option:selected').text() + "</b>?<br/>"
                                + "(It will NOT be possible to cancel it, and the Daemon will be stopped for the duration of the operation.)"
                                + "<br/><span class=\"danger\">Warning, this feature is still in BETA, use it at your own risk!</span>", function(result) {
                    if (!result)
                        return;
                    debugToggleIco(btn);
                    callDebugAjax({
                        data: data_to_send,
                        error: function (request, status, error) {
                            handleAjaxError(request, status, error);
                            debugToggleIco(btn);
                        },
                        success: function(data) {
                            if (data.state == 'ok') {
                                $.fn.showAlert({message: 'Backup restored successfully.', level: 'success'});
                            } else {
                                $.fn.showAlert({message: data.result, level: 'danger'});
                            }
                            debugToggleIco(btn);
                        }
                    });
                });
            }}
        });
    });

    // Download the selected jMQTT backup
    _root_div.off('click', 'a.backupJMqttDownload').on('click', 'a.backupJMqttDownload', function() {
        if (!$('#sel_backupJMqtt option:selected').length)
            return;
        window.open('core/php/downloadFile.php?pathfile=plugins/jMQTT/data/backup/' + $('#sel_backupJMqtt').value(), "_blank", null);
    });

    // Add a new jMQTT backup file by upload to the list
    $('#bt_backupJMqttUpload').fileupload({
        dataType: 'json',
        replaceFileInput: false,
        done: function(e, data) {
            if (data.result.state != 'ok') {
                $.fn.showAlert({message: data.result.result, level: 'danger'});
            } else {
                $('#sel_backupJMqtt').empty();
                for (var i in data.result.result) {
                    var oVal = data.result.result[i].name;
                    var oSize = ' (' + data.result.result[i].size +')';
                    $('#sel_backupJMqtt').prepend('<option selected value="' + oVal + '">' + oVal + oSize + '</option>');
                }
                $.fn.showAlert({message: 'File(s) successfully added', level: 'success'})
            }
            $('#bt_backupJMqttUpload').val(null);
        }
    });
}

function builder_updates(div) {
    let res = '<form class="form-horizontal"><fieldset><div class="form-group">';
    res += '<div class="col-lg-4">Select the update file to apply:</div>';
    res += '<div class="col-lg-5 input-group"><span class="input-group-btn">';
    res += '<select class="form-control reapplyUpdate roundedLeft">';
    <?php

        // List all migration files
        $update_dir = realpath(__DIR__ . '/../../resources/update/');
        $files = ls($update_dir, '*.php', false, array('files'));

        $migrations = array();
        foreach ($files as $name) {
            // Use only matching files
            if (!preg_match_all("/^(\d+)(\.(\d+)(\.(\d+))?)?.php$/", $name, $m))
                continue;
            $fileVer = intval($m[1][0]).'.'.intval($m[3][0]).'.'.intval($m[5][0]);
            $migrations[$fileVer] = $name;
        }

        // Reverse sort files by key (version number)
        function rev_version_compare($v1, $v2) { return -version_compare($v1, $v2); }
        uksort($migrations, 'rev_version_compare');

        // Apply migration files in the right order
        foreach ($migrations as $ver => $name) {
            echo "        res += '<option value=\"$name\">$ver</option>';\n";
        }

    ?>
    res += '</select></span><span class="input-group-btn">';
    res += '<a class="btn btn-success roundedRight reapplyUpdate" style="width:75px;">';
    res += '<i class="fas fa-arrow-circle-right"></i> Apply</a></span>';
    res += '</div></fieldset></form>';
    div.html(res);

    div.off('click', 'a.reapplyUpdate').on('click', 'a.reapplyUpdate', function() {
        callDebugAjax({
            data: {
                action: "reapplyUpdate",
                name: $(this).closest('form').find('select.reapplyUpdate').value()
            },
            error: function(error) {
                $.fn.showAlert({message: error, level: 'warning'})
            },
            success: function(data) {
                $.fn.showAlert({message: 'Update applied', level: 'success'});
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
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-circle-notch"></i> Jeedom general status</h3>
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
                                <label class="col-sm-3 control-label">Lang</label>
                                <div class="col-sm-3">
                                    <span><?php echo config::byKey('language'); ?></span>
                                </div>
                            </div>
                            <?php
                                $cuid = @cache::byKey('jMQTT::daemonUid')->getValue("0:0");
                                list($cpid, $cport) = array_map('intval', explode(":", $cuid));
                            ?>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">PID</label>
                                <div class="col-sm-3">
                                    <span><?php echo $cpid; ?></span>
                                </div>
                                <label class="col-sm-3 control-label">Port</label>
                                <div class="col-sm-3">
                                    <span><?php echo $cport; ?></span>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
<?php

// Create panels to edit simulate dangerous actions on jMQTT
panelCreator('Simulate internal actions',      'warning', 'fas fa-radiation-alt', 'builder_actions');

// Create panels to backup/restore jMQTT devices and configuration
panelCreator('Backup/Restore jMQTT',           'warning', 'fas fa-save',          'builder_backups');

// Create panels to edit Config values
panelCreator('Daemon config values',           'primary', 'fas fa-wrench',        'builder_configInt');
panelCreator('Brokers config values',          'primary', 'fas fa-wrench',        'builder_configBrk');
panelCreator('Equipments config values',       'primary', 'fas fa-wrench',        'builder_configEqp');
panelCreator('Info Commands config values',    'primary', 'fas fa-wrench',        'builder_configCmdI');
panelCreator('Action Commands config values',  'primary', 'fas fa-wrench',        'builder_configCmdA');

// Get Jeedom plugin descriptor
$jplugin = update::byLogicalId("jMQTT");

?>
        </div>
        <div class="col-md-6 col-sm-12"><!-- General status of jMQTT -->
            <div class="panel panel-success">
                <div class="panel-heading">
                    <h3 class="panel-title"><i class="fas fa-certificate"></i> jMQTT general status</h3>
                </div>
                <div class="panel-body">
                    <form class="form-horizontal">
                        <fieldset>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">Source</label>
                                <div class="col-sm-3">
                                    <span><?php echo $jplugin->getSource(); ?></span>
                                </div>
                                <label class="col-sm-3 control-label">LogicalId</label>
                                <div class="col-sm-3">
                                    <span><?php echo $jplugin->getLogicalId(); ?></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">Installed version</label>
                                <div class="col-sm-9">
                                    <span><?php echo $jplugin->getLocalVersion(); ?></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-sm-3 control-label">Remote version</label>
                                <div class="col-sm-9">
                                    <span><?php echo $jplugin->getRemoteVersion(); ?></span>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
<?php

// Create panels to simulate send to daemon
panelCreator('Simulate comm. with Daemon',     'danger',  'fas fa-exchange-alt',  'builder_daemon');
panelCreator('Reapply upgrade script',         'danger',  'techno-fleches',       'builder_updates');

// Create panels to edit Cache values
panelCreator('Daemon cache values',            'primary', 'fas fa-book',          'builder_cacheInt');
panelCreator('Brokers cache values',           'primary', 'fas fa-book',          'builder_cacheBrk');
panelCreator('Equipments cache values',        'primary', 'fas fa-book',          'builder_cacheEqp');
panelCreator('Info Commands cache values',     'primary', 'fas fa-book',          'builder_cacheCmdI');
panelCreator('Action Commands cache values',   'primary', 'fas fa-book',          'builder_cacheCmdA');

?>
        </div>
    </div>

    <!-- New empty section -->
    <div class="row">
        <div class="col-md-6 col-sm-12">
<?php

?>
        </div><div class="col-md-6 col-sm-12">
<?php

?>
        </div>
    </div>
    <!-- /New empty section -->

    <script>
// Function to hide, show and build sections content on the fly
$('a.btn.btn-info.btn-show-hide').on('click', function () {
    let head = $(this).closest('div.panel');
    let div = head.find('div.panel-body');
    if ($(this).hasClass('closed')) {
        head.find('div.panel-heading').addClass('rounded');
        $(this).removeClass('closed').html('<i class="fas fa-search-plus"></i> Show');
        div.addClass('hidden');
        if ($(this).hasAttr('builder'))
            div.empty();
    } else {
        $(this).addClass('closed').html('<i class="fas fa-search-minus"></i> Hide');
        head.find('div.panel-heading').removeClass('rounded');
        div.removeClass('hidden');
        if ($(this).hasAttr('builder')) {
            let builder = window[$(this).attr('builder')];
            if (typeof builder === 'function')
                builder(div);
        }
    }
});
    </script>
