<br/>
<div class="row">
	<div class="col-md-6 col-sm-12">
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
								<td><a class="btn btn-success btn-sm eqLogicAction" data-action="startMqttClient" style="position:relative;top:-5px;"><i class="fa fa-play"></i></a></td>
								<td class="mqttClientLastLaunch"></td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>
	</div>
	<div class="col-md-6 col-sm-12">
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
								<a class="btn btn-info eqLogicAction" data-action="modalViewLog" data-slaveId="-1" data-log=""></a>
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
							<label class="col-lg-4 control-label">{{IP/Nom de Domaine du Broker}} <sup><i class="fa fa-question-circle tooltips"
							title="{{IP/Nom de Domaine du Broker, par défaut 'localhost' i.e. la machine hébergeant Jeedom.}}"></i></sup></label>
							<div class="col-lg-4">
								<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttAddress"
									style="margin-top: 5px" placeholder="localhost" />
							</div>
						</div>
						<div class="form-group">
							<label class="col-lg-4 control-label">{{Port du Broker}} <sup><i class="fa fa-question-circle tooltips"
							title="{{Port réseau sur lequel écoute le Broker.<br/>Par défaut le port 1883 est utilisé pour MQTT en clair et 8883 pour MQTT sécurisé (TLS).}}"></i></sup></label>
							<div class="col-lg-4">
								<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttPort"
									style="margin-top: 5px" placeholder="{{Par défaut 1883 sans TLS et 8883 avec TLS}}" />
							</div>
						</div>
						<div class="form-group">
							<label class="col-lg-4 control-label">{{Identifiant/ClientId}} <sup><i class="fa fa-question-circle tooltips"
							title="{{Identifiant avec lequel l’équipement broker s’inscrit auprès du Broker MQTT.
							<br/>Cet identifiant est aussi utilisé dans les topics status et api.
							<br/>Il est important que cet identifiant ne soit utilisé que par jMQTT sur ce Broker.}}"></i></sup></label>
							<div class="col-lg-4">
								<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttId"
									style="margin-top: 5px" placeholder="jeedom" />
							</div>
						</div>
						<div class="form-group">
							<label class="col-lg-4 control-label">{{Nom d'utilisateur}} <sup><i class="fa fa-question-circle tooltips"
							title="{{Utilisateur permettant de se connecter au Broker.<br/>Non obligatoire.}}"></i></sup></label>
							<div class="col-lg-4">
								<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttUser"
									autocomplete="off" style="margin-top: 5px" />
							</div>
						</div>
						<div class="form-group">
							<label class="col-lg-4 control-label">{{Mot de passe}} <sup><i class="fa fa-question-circle tooltips"
							title="{{Mot de passe permettant de se connecter au Broker.<br/>Non obligatoire.}}"></i></sup></label>
							<div class="col-lg-4">
								<input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttPass"
									autocomplete="off" style="margin-top: 5px" />
							</div>
						</div>
						<div class="form-group">
							<label class="col-lg-4 control-label">{{Publier le statut}} <sup><i class="fa fa-question-circle tooltips"
							title="{{Active/Désactive la publication du statut en MQTT sur le Broker (sur le topic {ClientId}/status).}}"></i></sup></label>
							<div class="col-lg-4">
								<input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttPubStatus" checked>
							</div>
						</div>
						<div class="form-group">
							<label class="col-lg-4 control-label">{{Topic de souscription en mode inclusion automatique des équipements}} <sup><i class="fa fa-question-circle tooltips"
							title="{{Souscris uniquement aux Topics correspondants sur ce Broker. '#' par défaut, i.e. tous les Topics.
							<br/>Ne pas modifier sans en comprendre les implications.}}"></i></sup></label>
							<div class="col-lg-4">
								<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttIncTopic"
									style="margin-top: 5px" placeholder="#" />
							</div>
						</div>
						<div class="form-group">
							<label class="col-lg-4 control-label">{{MQTTS (MQTT over TLS)}} <sup><i class="fa fa-question-circle tooltips"
							title="{{Active le chiffrement TLS des communications avec le Broker. Pour plus d'information, se référer à la documentation.}}"></i></sup></label>
							<div class="col-lg-4">
								<input id="fTls" type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTls">
							</div>
						</div>
						<div id="jmqttDivTls" style="display:none">
							<div class="form-group">
								<label class="col-lg-4 control-label">{{Vérifier le certificat du Broker}} <sup><i class="fa fa-question-circle tooltips"
								title="{{Vérifie la chaîne d'approbation du certificat présenté par le Broker et que son sujet corresponde à l'IP/Nom de Domaine du Broker.}}"></i></sup></label>
								<div class="col-lg-4">
									<select id="fTlsCheck" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsCheck">
										<option value="public">{{Activé - Autorités Publiques}}</option>
										<option value="private">{{Activé - Autorité Personnalisée}}</option>
										<option value="disabled">{{Désactivé - Non Recommandé}}</option>
									</select>
								</div>
								<span class="btn btn-success btn-sm btn-file" style="position:relative;top:2px !important;" title="{{Téléverser un Certificat}}">
									<i class="fas fa-upload"></i><input id="mqttUploadFile" type="file" name="file" accept=".crt, .pem, .key" data-url="plugins/jMQTT/core/ajax/jMQTT.ajax.php?action=fileupload&dir=certs">
								</span>
							</div>
<?php
	$dir = realpath(dirname(__FILE__) . '/../../' . jMQTT::PATH_CERTIFICATES);
	$crtfiles = "";
	$pemfiles = "";
	$keyfiles = "";
	foreach (ls($dir, '*') as $file) {
		if (strpos($file,'.crt') !== false)
			$crtfiles .= str_repeat(' ', 36) . '<option value="' . $file . '">' .$file . '</option>';
		elseif (strpos($file,'.pem') !== false)
			$pemfiles .= str_repeat(' ', 36) . '<option value="' . $file . '">' .$file . '</option>';
		elseif (strpos($file,'.key') !== false)
			$keyfiles .= str_repeat(' ', 36) . '<option value="' . $file . '">' .$file . '</option>';
	}
?>
							<div id="jmqttDivTlsCa" class="form-group">
								<label class="col-lg-4 control-label">{{Autorité Personnalisée}} <sup><i class="fa fa-question-circle tooltips"
								title="{{Sélectionne l'autorité de certification attendue pour le Broker.<br/>Les certificats peuvent être envoyés sur Jeedom avec le bouton vert ci-dessus.<br/>Il est possible de supprimer des Certificats depuis la page de configuration générale du Plugin.}}"></i></sup></label>
								<div class="col-lg-4">
									<select id="fTlsCaFile" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsCaFile" style="margin-top: 5px">
										<option value="">{{Désactivé}}</option>
<?php echo $crtfiles; ?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-lg-4 control-label">{{Certificat Client}} <sup><i class="fa fa-question-circle tooltips"
								title="{{Sélectionne le Certificat Client attendu par le Broker.<br/>Ce Certificat doit être associé à la Clé Privée, dans le champ qui apparaîtra en-dessous, si l'un est fourni l'autre est obligatoire.}}"></i></sup></label>
								<div class="col-lg-4">
									<select id="fTlsClientCertFile" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsClientCertFile" style="margin-top: 5px">
										<option value="">{{Désactivé}}</option>
<?php echo $pemfiles; ?>
									</select>
								</div>
							</div>
							<div id="jmqttDivTlsClientKey" class="form-group">
								<label class="col-lg-4 control-label">{{Clé Privée Client}} <sup><i class="fa fa-question-circle tooltips"
								title="{{Sélectionne la Clée Privée du Client permettant de discuter avec le Broker.<br/>Cette Clé Privée doit être associée au Certificat au-dessus, si l'un est fourni l'autre est obligatoire.}}"></i></sup></label>
								<div class="col-lg-4">
									<select id="fTlsClientKeyFile" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsClientKeyFile" style="margin-top: 5px">
										<option value="">{{Désactivé}}</option>
<?php echo $keyfiles; ?>
									</select>
								</div>
							</div>
						</div>
						<div class="form-group">
							<label class="col-lg-4 control-label">{{Accès API}} <sup><i class="fa fa-question-circle tooltips"
							title="{{Permet d’accéder à toutes les méthodes de l’API JSON RPC au travers du protocole MQTT.<br/>Pour plus d'information, se référer à la documentation.}}"></i></sup></label>
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

