<br/>
<div class="row">
    <div class="col-md-5 col-sm-12">
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
                                <th>{{Dernier lancement}}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="daemonLaunchable"></td>
                                <td class="daemonState"></td>
                                <td><a class="btn btn-success btn-sm bt_startDaemon" style="position:relative;top:-5px;"><i class="fa fa-play"></i></a></td>
                                <td class="daemonLastLaunch"></td>
                            </tr>
                        </tbody>
                    </table>                                    
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-7 col-sm-12">
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
                            <label class="col-sm-3 control-label">{{Niveau log}}</label>
                            <div class="col-sm-9">
                                <label class="radio-inline"><input type="radio" class="configKey" name="rd_logupdate" data-l1key="" data-l2key="1000" /> {{Aucun}}</label>
                                <label class="radio-inline"><input type="radio" class="configKey" name="rd_logupdate" data-l1key="" data-l2key="default" /> {{Defaut}}</label>
                                <label class="radio-inline"><input type="radio" class="configKey" name="rd_logupdate" data-l1key="" data-l2key="100" /> {{Debug}}</label>
                                <label class="radio-inline"><input type="radio" class="configKey" name="rd_logupdate" data-l1key="" data-l2key="200" /> {{Info}}</label>
                                <label class="radio-inline"><input type="radio" class="configKey" name="rd_logupdate" data-l1key="" data-l2key="300" /> {{Warning}}</label>
                                <label class="radio-inline"><input type="radio" class="configKey" name="rd_logupdate" data-l1key="" data-l2key="400" /> {{Error}}</label>
                            </div>
                        </fieldset>
                        <fieldset>
                            <label class="col-sm-3 control-label">{{Logs}}</label>
                            <div class="col-sm-9">
                                <a class="btn btn-info bt_plugin_conf_view_log" data-slaveId="-1" data-log=""></a>
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
                                    style="margin-top: 5px" placeholder="jeedom" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Compte de Connexion (non obligatoire) : }}</label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttUser"
                                    autocomplete="off" style="margin-top: 5px" placeholder="jeedom" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Mot de passe de Connexion (non obligatoire) : }}</label>
                            <div class="col-lg-4">
                                <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttPass"
                                    autocomplete="off" style="margin-top: 5px" placeholder="jeedom" />
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
                                    <option value="disable">{{Désactivé}}</option>
                                    <option value="enable">{{Activé}}</option>
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

function showDaemonInfo(data) {
    switch(data.launchable) {
        case 'ok':
            $('.bt_startDaemon').show();
            $('.daemonLaunchable').empty().append('<span class="label label-success" style="font-size:1em;">{{OK}}</span>');
            break;
        case 'nok':
            $('.bt_startDaemon').hide();
            $('.daemonLaunchable').empty().append('<span class="label label-danger" style="font-size:1em;">{{NOK}}</span> ' + data.message);
            break;
        default:
           $('.daemonLaunchable').empty().append('<span class="label label-warning" style="font-size:1em;">' + data.state + '</span>');
    }

    switch (data.state) {
        case 'ok':
            $('.daemonState').empty().append('<span class="label label-success" style="font-size:1em;">{{OK}}</span>');
            $("#div_broker_daemon").closest('.panel').removeClass('panel-warning').removeClass('panel-danger').addClass('panel-success');
            break;
        case 'pok':
            $('.daemonState').empty().append('<span class="label label-warning" style="font-size:1em;">{{POK}}</span> ' + data.message);
            $("#div_broker_daemon").closest('.panel').removeClass('panel-danger').removeClass('panel-success').addClass('panel-warning');
            break;
        case 'nok':
            $('.daemonState').empty().append('<span class="label label-danger" style="font-size:1em;">{{NOK}}</span> ' + data.message);
            $("#div_broker_daemon").closest('.panel').removeClass('panel-warning').removeClass('panel-success').addClass('panel-danger');
            break;
        default:
            $('.daemonState').empty().append('<span class="label label-warning" style="font-size:1em;">'+data.state+'</span>');
    }
    
    $('.daemonLastLaunch').empty().append(data.last_launch);
    
    if ($("#div_broker_daemon").is(':visible')) {
        clearTimeout(timeout_refreshDaemonInfo);
        timeout_refreshDaemonInfo = setTimeout(refreshDaemonInfo, 5000);
    }
}

function refreshDaemonInfo() {
    var id = $('.eqLogicAttr[data-l1key=id]').value();
    if (id == undefined || id == "" || $('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val() != 'broker')
        return;

    callPluginAjax({
        data: {
            action: 'getDaemonInfo',
            id: id,
        },
        success: function(data) {
            showDaemonInfo(data);
        }
    });
}

// Observe attribute change of #brokertab. When tab is made visible, trigger refreshDaemonInfo
var observer = new MutationObserver(function(mutations) {
  mutations.forEach(function(mutation) {
      if ($("#brokertab").is(':visible')) {
          refreshDaemonInfo();
      }
  });    
});
observer.observe($("#brokertab")[0], {attributes: true});

$('body').off('jMQTT::EventState').on('jMQTT::EventState', function (_event,_options) {
    showDaemonInfo(_options);
});

$('.bt_startDaemon').on('click',function(){
    var id = $('.eqLogicAttr[data-l1key=id]').value();
    if (id == undefined || id == "" || $('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val() != 'broker')
        return;

    clearTimeout(timeout_refreshDaemonInfo);
    callPluginAjax({
        data: {
            action: 'daemonStart',
            id: id,
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

