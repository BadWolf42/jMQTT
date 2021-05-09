<br/>
<div class="row">
    <div class="col-md-5 col-sm-12">
        <div class="panel panel-success">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-university"></i> {{Client MQTT}}
                </h3>
            </div>
            <div class="panel-body">
                <div id="div_broker_mqttclient">
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
                                <td class="mqttClientLaunchable"></td>
                                <td class="mqttClientState"></td>
                                <td><a class="btn btn-success btn-sm bt_startMqttClient" style="position:relative;top:-5px;"><i class="fa fa-play"></i></a></td>
                                <td class="mqttClientLastLaunch"></td>
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
            <!-- <div class="form-group pull-right">
                <span class="btn btn-success btn-file" style="width:100%;" title="Uploader un fichier">
                    <i class="fas fa-upload"></i><input id="mqttUploadFile" type="file" name="file" data-url="plugins/jMQTT/core/ajax/jMQTT.ajax.php?action=fileupload&dir=certs">
                </span>
            </div> -->
            <form class="form-horizontal">
                <div class="form-group">
                    <fieldset>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{IP/Nom de Domaine du Broker}} <sup><i class="fa fa-question-circle tooltips"
                            title="IP/Nom de Domaine du Broker, par défaut 'localhost' i.e. la machine hébergeant Jeedom.
                            <br/>Pour MQTT sécurisé (TLS) avec validation du certificat, le certificat doit être valide pour le contenu de ce champ."></i></sup></label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttAddress"
                                    style="margin-top: 5px" placeholder="localhost" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Port du Broker}} <sup><i class="fa fa-question-circle tooltips"
                            title="Port réseau sur lequel écoute le Broker.<br/>Par défaut le port 1883 est utilisé pour MQTT en clair et 8883 pour MQTT sécurisé (TLS)."></i></sup></label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttPort"
                                    style="margin-top: 5px" placeholder="Par défaut 1883 sans TLS et 8883 avec TLS" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Identifiant de Connexion}} <sup><i class="fa fa-question-circle tooltips"
                            title="Identifiant avec lequel l’équipement broker s’inscrit auprès du Broker MQTT. Par défaut la valeur est 'jeedom'.
                            <br/>Cet identifiant est aussi utilisé dans les topics des commandes info status et api.
                            <br/>Les topics sont automatiquement mis à jour si l’identifiant est modifié.
                            <br/>Il est important que cet identifiant ne soit utilisé que par jMQTT sur ce Broker."></i></sup></label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttId"
                                    style="margin-top: 5px" placeholder="jeedom" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Compte de Connexion}} <sup><i class="fa fa-question-circle tooltips"
                            title="Utilisateur permettant de se connecter au Broker.<br/>Non obligatoire, vide par défaut."></i></sup></label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttUser"
                                    autocomplete="off" style="margin-top: 5px" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Mot de passe de Connexion}} <sup><i class="fa fa-question-circle tooltips"
                            title="Mot de passe permettant de se connecter au Broker.<br/>Non obligatoire, vide par défaut."></i></sup></label>
                            <div class="col-lg-4">
                                <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttPass"
                                    autocomplete="off" style="margin-top: 5px" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Topic de souscription en mode inclusion automatique des équipements}} <sup><i class="fa fa-question-circle tooltips"
                            title="Seul les Topics correspondants pourront être souscrits sur ce Broker. '#' par défaut, i.e. tous les Topics.
                            <br/>Ne pas modifier sans en comprendre les implications."></i></sup></label>
                            <div class="col-lg-4">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttIncTopic"
                                    style="margin-top: 5px" placeholder="#" />
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{MQTT sécurisé (TLS)}} <sup><i class="fa fa-question-circle tooltips"
                            title="Chiffrement TLS des communications avec le Broker. Pour plus d'information, se référer à la documentation.
                            <br/>Ne pas modifier sans en comprendre les implications."></i></sup></label>
                            <div class="col-lg-4">
                                <select id="fTls" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTls" style="margin-top: 5px">
                                    <option value="disable">{{Désactivé}}</option>
                                    <option value="enable">{{Activé - Autorités Publiques}}</option>
                                    <option value="custom">{{Activé - Autorité Personnalisée}}</option>
                                </select>
                            </div>
                        </div>
                        <div id="dTlsSecure" class="form-group">
                            <label class="col-lg-4 control-label">{{Vérifier l'Autorité}} <sup><i class="fa fa-question-circle tooltips"
                            title="Vérifie que le certificat du Broker est valide et correspond bien à ce Broker (IP/FQDN & CA)."></i></sup></label>
                            <div class="col-lg-4">
                                <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsSecure">
                            </div>
                        </div>
                        <div id="dTlsCaFile" class="form-group">
<?php
    $dir = dirname(__FILE__) . '/../../data/certs';
?>
                            <label class="col-lg-4 control-label">{{Autorité Personnalisée}} <sup><i class="fa fa-question-circle tooltips"
                            title="Selectionne l'autorité de certification attendue pour le Broker.<br/>Les certificats doivent être dans : <?php echo $dir; ?>."></i></sup></label>
                            <div class="col-lg-4">
                                <!-- <select id="fTlsCaFile" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsCaFile" style="width:90%; margin-top: 5px"> -->
                                <select id="fTlsCaFile" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsCaFile" style="margin-top: 5px">
<?php
    foreach (ls($dir, '*') as $file) {
        if (strpos($file,'.crt') !== false) {
            echo '                                    <option value="' . $file . '">' .$file . '</option>';
        }
    }
?>
                                </select>
                               <!-- <span class="btn btn-danger" style="" title="Supprimer le Certificat selectionné">
                                    <a class="mqttDeleteFile"><i class="fas fa-trash"></i></a>
                                </span>
                                <span class="btn btn-success btn-file" style="width:5%;" title="Uploader un Certificat">
                                    <i class="fas fa-upload"></i><input id="mqttUploadFile" type="file" name="file" data-url="plugins/jMQTT/core/ajax/jMQTT.ajax.php?action=fileupload&dir=certs">
                                </span>
                                <div class="eventDisplayMini"></div> -->
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Accès API}} <sup><i class="fa fa-question-circle tooltips"
                            title="Permet d’accéder à toutes les méthodes de l’API JSON RPC au travers du protocole MQTT.<br/>Pour plus d'information, se référer à la documentation."></i></sup></label>
                            <div class="col-lg-4">
                                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="api" style="margin-top: 5px">
                                    <option value="disable">{{Désactivé}}</option>
                                    <option value="enable">{{Activé}}</option>
                                </select>
                            </div>
                        </div>
                        <!-- <div class="form-group">
                            <label class="col-lg-4 control-label">{{PAHO Logging}} </label>
                            <div class="col-lg-4">
                                <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttPahoLog" style="margin-top: 5px">
                                    <option value="">None</option>
                                    <option value="debug">logging.DEBUG</option>
                                    <option value="info">logging.INFO</option>
                                    <option value="warning">logging.WARNING</option>
                                    <option value="error">logging.ERROR</option>
                                    <option value="critical">logging.CRITICAL</option>
                                </select>
                            </div>
                        </div> -->
                    </fieldset>
                </div>
            </form>
        </div>
    </div>
</div>

<script>

var timeout_refreshMqttClientInfo = null;

function showMqttClientInfo(data) {
    switch(data.launchable) {
        case 'ok':
            $('.bt_startMqttClient').show();
            $('.mqttClientLaunchable').empty().append('<span class="label label-success" style="font-size:1em;">{{OK}}</span>');
            break;
        case 'nok':
            $('.bt_startMqttClient').hide();
            $('.mqttClientLaunchable').empty().append('<span class="label label-danger" style="font-size:1em;">{{NOK}}</span> ' + data.message);
            break;
        default:
           $('.mqttClientLaunchable').empty().append('<span class="label label-warning" style="font-size:1em;">' + data.state + '</span>');
    }

    switch (data.state) {
        case 'ok':
            $('.mqttClientState').empty().append('<span class="label label-success" style="font-size:1em;">{{OK}}</span>');
            $("#div_broker_mqttclient").closest('.panel').removeClass('panel-warning').removeClass('panel-danger').addClass('panel-success');
            break;
        case 'pok':
            $('.mqttClientState').empty().append('<span class="label label-warning" style="font-size:1em;">{{POK}}</span> ' + data.message);
            $("#div_broker_mqttclient").closest('.panel').removeClass('panel-danger').removeClass('panel-success').addClass('panel-warning');
            break;
        case 'nok':
            $('.mqttClientState').empty().append('<span class="label label-danger" style="font-size:1em;">{{NOK}}</span> ' + data.message);
            $("#div_broker_mqttclient").closest('.panel').removeClass('panel-warning').removeClass('panel-success').addClass('panel-danger');
            break;
        default:
            $('.mqttClientState').empty().append('<span class="label label-warning" style="font-size:1em;">'+data.state+'</span>');
    }

    $('.mqttClientLastLaunch').empty().append(data.last_launch);

    if ($("#div_broker_mqttclient").is(':visible')) {
        clearTimeout(timeout_refreshMqttClientInfo);
        timeout_refreshMqttClientInfo = setTimeout(refreshMqttClientInfo, 5000);
    }
}

function refreshMqttClientInfo() {
    var id = $('.eqLogicAttr[data-l1key=id]').value();
    if (id == undefined || id == "" || $('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val() != 'broker')
        return;

    callPluginAjax({
        data: {
            action: 'getMqttClientInfo',
            id: id,
        },
        success: function(data) {
            showMqttClientInfo(data);
        }
    });
}

// Observe attribute change of #brokertab. When tab is made visible, trigger refreshMqttClientInfo
var observer = new MutationObserver(function(mutations) {
  mutations.forEach(function(mutation) {
      if ($("#brokertab").is(':visible')) {
          refreshMqttClientInfo();
      }
  });
});
observer.observe($("#brokertab")[0], {attributes: true});

$('body').off('jMQTT::EventState').on('jMQTT::EventState', function (_event,_options) {
    showMqttClientInfo(_options);
});

$('.bt_startMqttClient').on('click',function(){
    var id = $('.eqLogicAttr[data-l1key=id]').value();
    if (id == undefined || id == "" || $('.eqLogicAttr[data-l1key=configuration][data-l2key=type]').val() != 'broker')
        return;

    clearTimeout(timeout_refreshMqttClientInfo);
    callPluginAjax({
        data: {
            action: 'startMqttClient',
            id: id,
        },
        success: function(data) {
            refreshMqttClientInfo();
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

$('#fTls').change(function(){
    if ($("#fTls").val() == 'disable') {
        $('#dTlsSecure').hide();
    } else {
        $('#dTlsSecure').show();
    }
    if ($("#fTls").val() == 'custom') {
        $('#dTlsCaFile').show();
    } else {
        $('#dTlsCaFile').hide();
    }
});

$('#mqttUploadFile').fileupload({
  dataType: 'json',
  replaceFileInput: false,
  done: function (e, data) {
    if (data.result.state != 'ok') {
      $('.eventDisplayMini').showAlert({message: data.result.result, level: 'danger'});
      return;
    }
   $('.eventDisplayMini').showAlert({message: '{{Fichier(s) ajouté(s) avec succès}}', level: 'success'});
   // $('#md_modal').dialog('close');
	// $('#md_modal').dialog({title: "{{Gestion des jingles SqueezeboxControl}}"});
	// $('#md_modal').load('index.php?v=d&plugin=squeezeboxcontrol&modal=jingle').dialog('open');
  }
});

$('.mqttDeleteFile').on('click', function (){
var oriname = $("#fTlsCaFile").val()
 $.ajax({
	type: "POST",
	url: "plugins/jMQTT/core/ajax/jMQTT.ajax.php",
	data: {
		action: "filedelete",
		dir: "certs",
		name: oriname
	},
	global : false,
	dataType: 'json',
	error: function(request, status, error) {
		handleAjaxError(request, status, error);
	},
	success: function(data) {
		if (data.state != 'ok') {
			$('.eventDisplayMini').showAlert({message:  data.result,level: 'danger'});
			setTimeout(function() { deleteAlertMini() }, 2000);
			return;
		}
		$('.eventDisplayMini').showAlert({message:  'Suppression effectuée' ,level: 'success'});
		setTimeout(function() { deleteAlertMini() }, 2000);
		modifyWithoutSave=false;
		// $('#md_modal').dialog('close');
		// $('#md_modal').dialog({title: "{{Gestion des jingles SqueezeboxControl}}"});
		// $('#md_modal').load('index.php?v=d&plugin=squeezeboxcontrol&modal=jingle').dialog('open');
	}
});
});
</script>

