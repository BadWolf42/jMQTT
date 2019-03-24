<br/>
<div class="row">
    <div class="col-md-8 col-sm-12">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-university"></i> {{Démon}}
                </h3>
            </div>
            <div class="panel-body">
                <div id="div_broker_daemon">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>{{Configuration}}</th>
                                <th>{{Statut}}</th>
                                <th>{{(Re)Démarrer}}</th>
                                <th>{{Arrêter}}</th>
                                <th>{{Gestion automatique}}</th>
                                <th>{{Dernier lancement}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="daemonLaunchable"></td>
                                <td class="daemonState"></td>
                                <td><a class="btn btn-success btn-sm bt_startDaemon" style="position:relative;top:-5px;"><i class="fa fa-play"></i></a></td>
                                <td><a class="btn btn-danger btn-sm bt_stopDaemon" style="position:relative;top:-5px;"><i class="fa fa-stop"></i></a></td>
                                <td><a class="btn btn-sm bt_changeAutoMode" style="position:relative;top:-5px;"></a></td>
                                <td class="daemonLastLaunch"></td>
                            </tr>
                        </tbody>
                    </table>                                    
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4 col-sm-12">
        <div class="panel panel-primary" id="div_brokerLog">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-file-o"></i> {{Log}}
                </h3>
            </div>
            <div class="panel-body">
                <div id="div_broker_log">
                    <form class="form-horizontal">
                        <fieldset>
                            <label class="col-sm-2 control-label">{{Logs}}</label>
                            <div class="col-sm-10">
                                <a class="btn btn-info bt_plugin_conf_view_log"
                                    data-slaveId="-1" data-log=""></a>
                            </div>
                        </fieldset>
                    </form>
                </div>
                <div class="form-actions"></div>
            </div>
        </div>
    </div>
</div>
<div class="panel panel-primary">
    <div class="panel-heading">
        <h3 class="panel-title">
            <i class="fa fa-cogs"></i> {{Configuration}}
        </h3>
    </div>
    <div class="panel-body">
        <div id="div_broker_configuration"></div>
        <div class="form-actions">
            <form class="form-horizontal">
                <div class="form-group">
                    <fieldset>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{IP de Mosquitto : }}</label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttAddress"
                                    style="margin-top: 5px" placeholder="localhost" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Port de Mosquitto : }}</label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttPort"
                                    style="margin-top: 5px" placeholder="1883" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Identifiant de Connexion : }}</label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttId"
                                    style="margin-top: 5px" placeholder="Jeedom" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Compte de Connexion (non obligatoire) : }}</label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttUser"
                                    style="margin-top: 5px" placeholder="Jeedom" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Mot de passe de Connexion (non obligatoire) : }}</label>
                            <div class="col-lg-4">
                                <input type="password" class="eqLogicAttr form-control"
                                    data-l1key="configuration" data-l2key="mqttPass" style="margin-top: 5px" placeholder="Jeedom" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Topic de souscription en mode inclusion automatique
                                des équipements : }}</label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttIncTopic"
                                    style="margin-top: 5px" placeholder="{{# par défaut - ne pas modifier sans connaître}}" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Accès API : }}</label>
                            <div class="col-lg-4">
                                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="api" style="margin-top: 5px">
                                    <option value="enable">{{Activé}}</option>
                                    <option value="disable">{{Désactivé}}</option>
                                </select>
                            </div>
                        </div>
                    </fieldset>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

var timeout_refreshDaemonInfo = null;

function callJmqttAjax(_params) {
    var params = $.extend({global: false}, jeedom.private.default_params, _params || {});
    var paramsAJAX = jeedom.private.getParamsAJAX(params);
    paramsAJAX.url = 'plugins/jMQTT/core/ajax/jMQTT.ajax.php';
    paramsAJAX.data = _params.data;
    $.ajax(paramsAJAX);
}

function refreshDaemonInfo() {
    var id = getUrlVars('id');
    if (id == false)
        return;

    callJmqttAjax({
        data: {
            action: 'getDaemonInfo',
            id: id,
        },
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            console.log(data);
    		var nok = false;          
            switch(data.launchable) {
                case 'ok':
                    $('.bt_startDaemon').show();
                    $('.daemonLaunchable').empty().append('<span class="label label-success" style="font-size:1em;">{{OK}}</span>');
                    break;
                case 'nok':
                    if(data.auto == 1) {
                        nok = true;
                    }
                    $('.bt_startDaemon').hide();
                    $('.bt_stopDaemon').hide();
                    $('.daemonLaunchable').empty().append('<span class="label label-danger" style="font-size:1em;">{{NOK}}</span> ' + data.message);
                    break;
                default:
                   $('.daemonLaunchable').empty().append('<span class="label label-warning" style="font-size:1em;">' + data.state + '</span>');
            }

            switch (data.state) {
                case 'ok':
                    $('.daemonState').empty().append('<span class="label label-success" style="font-size:1em;">{{OK}}</span>');
                    break;
                case 'pok':
                    if (data.auto == 1) {
                        nok = true;
                    }
                    $('.daemonState').empty().append('<span class="label label-warning" style="font-size:1em;">{{POK}}</span> ' + data.message);
                    break;
                case 'nok':
                    if (data.auto == 1) {
                        nok = true;
                    }
                    $('.daemonState').empty().append('<span class="label label-danger" style="font-size:1em;">{{NOK}}</span> ' + data.message);
                    break;
                default:
                    $('.daemonState').empty().append('<span class="label label-warning" style="font-size:1em;">'+data.state+'</span>');
            }
            
            $('.daemonLastLaunch').empty().append(data.last_launch);
            if (data.auto == 1) {
                $('.bt_stopDaemon').hide();
                $('.bt_changeAutoMode').removeClass('btn-success').addClass('btn-danger');
                $('.bt_changeAutoMode').attr('data-mode',0);
                $('.bt_changeAutoMode').html('<i class="fa fa-times"></i> {{Désactiver}}');
            }
            else {
                if (data.launchable == 'ok' && data.state != 'nok') {
                    $('.bt_stopDaemon').show();
                }
                $('.bt_changeAutoMode').removeClass('btn-danger').addClass('btn-success');
                $('.bt_changeAutoMode').attr('data-mode',1);
                $('.bt_changeAutoMode').html('<i class="fa fa-magic"></i> {{Activer}}');
            }
            
            if (!nok) {
                $("#div_broker_daemon").closest('.panel').removeClass('panel-danger').addClass('panel-success');
            }
            else {
                $("#div_broker_daemon").closest('.panel').removeClass('panel-success').addClass('panel-danger');
            }
    
            if ($("#div_broker_daemon").is(':visible')) {
                console.log('#div_broker_daemon is visible');
                timeout_refreshDaemonInfo = setTimeout(refreshDaemonInfo, 5000);
            }
        }
    });
}

// if (document.location.hash == "#brokertab")
//     refreshDaemonInfo();

$('.bt_startDaemon').on('click',function(){
    clearTimeout(timeout_refreshDaemonInfo);
    callJmqttAjax({
        data: {
            action: 'daemonStart',
            id: getUrlVars('id'),
        },
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            refreshDaemonInfo();
        }
    });
});

$('.bt_stopDaemon').on('click',function(){
    clearTimeout(timeout_refreshDaemonInfo);
    callJmqttAjax({
        data: {
            action: 'daemonStop',
            id: getUrlVars('id'),
        },
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            refreshDaemonInfo();
        }
    });
});

$('.bt_changeAutoMode').on('click',function(){
    clearTimeout(timeout_refreshDaemonInfo);
    callJmqttAjax({
        data: {
            action: 'daemonChangeAutoMode',
            id: getUrlVars('id'),
            mode: $(this).attr('data-mode')
        },
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function(data) {
            refreshDaemonInfo();
        }
    });
});

$('#div_broker_log').on('click','.bt_plugin_conf_view_log',function() {
    if($('#md_modal').is(':visible')){
        $('#md_modal2').dialog({title: "{{Log du plugin}}"});
        $("#md_modal2").load('index.php?v=d&modal=log.display&log='+$(this).attr('data-log')+'&slaveId='+$(this).attr('data-slaveId')).dialog('open');
    }
    else{
        $('#md_modal').dialog({title: "{{Log du plugin}}"});
        $("#md_modal").load('index.php?v=d&modal=log.display&log='+$(this).attr('data-log')+'&slaveId='+$(this).attr('data-slaveId')).dialog('open');
    }
});

</script>

