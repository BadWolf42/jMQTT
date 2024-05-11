<?php

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
            if(typeof _buttons === 'function')
                _buttons(_div);
        }
    });
}

function configIntButtons(div) {
    div.off('click', 'a.add').on('click', 'a.add', function() {
        bootbox.confirm({
            title: 'Add an internal configuration parameter',
            message: '<label class="control-label">Key: </label> '
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" autofill="off" type="text" id="debugKey"><br/><br/>'
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
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" autofill="off" type="text" id="debugKey"><br/><br/>'
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
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" autofill="off" type="text" id="debugKey"><br/><br/>'
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
                    + '<input class="bootbox-input bootbox-input-text form-control" autocomplete="nope" autofill="off" type="text" id="debugKey"><br/><br/>'
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

function builder_toJeedom(div) {
    var res = '<form class="form-horizontal"><fieldset>';
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
    div.append('<div class="col-sm-12" style="height:15px">&nbsp;</div>'); // Spacer
    _root_div.append(div);

    _root_div.append('<legend style="margin-bottom:2px!important"><i class="fas fa-tools"></i> Troubleshooting</legend>');
    div = $('<div class="form-group">');
    add_action_event(div, 'hbStop',           'danger',  'fas fa-stop',                        'Stop Heatbeats');
    add_action_event(div, 'threadDump',       'info',    'kiko-zoom',                          'Ask the daemon for a "Thread Dump"');
    add_action_event(div, 'logVerbose',       'info',    'far fa-file',                        'VERBOSE logs');
    add_action_event(div, 'statsSend',        'info',    'fas fa-satellite',                   'Send stats');
    div.append('<div class="col-sm-12" style="height:10px">&nbsp;</div>'); // Last spacer
    _root_div.append(div);
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
                            <div class="form-group">
                                <label class="col-sm-3 control-label">PID</label>
                                <div class="col-sm-3">
                                    <span><?php echo jMQTTDaemon::getPid(); ?></span>
                                </div>
                                <label class="col-sm-3 control-label">Port</label>
                                <div class="col-sm-3">
                                    <span><?php echo jMQTTDaemon::getPort(); ?></span>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                </div>
            </div>
<?php

// Create panels to edit simulate dangerous actions on jMQTT
panelCreator('Simulate internal actions',      'warning', 'fas fa-radiation-alt', 'builder_actions');

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
panelCreator('Simulate comm. from Daemon',     'danger',  'fas fa-exchange-alt',  'builder_toJeedom');
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
            if(typeof builder === 'function')
                builder(div);
        }
    }
});
    </script>
