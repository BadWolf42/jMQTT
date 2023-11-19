<br/>
<div class="row">
    <div class="col-md-6 col-sm-12">
        <div class="panel panel-success mqttClientPanel">
            <div class="panel-heading">
                <h3 class="panel-title">
                    <i class="fa fa-university"></i> {{Client MQTT}}
                </h3>
            </div>
            <div class="panel-body">
                <div>
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
                                <td class="mqttClientLaunchable"><span class="label" style="font-size:1em;"></span><span class="state"></span></td>
                                <td class="mqttClientState"><span class="label" style="font-size:1em;"></span><span class="state"></span></td>
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
                    <i class="far fa-file"></i> {{Log}}
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
                    </form><br/>
                </div>
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

                <div class="form-group col-lg-6">
                    <fieldset>
                        <legend><i class="fas fa-rss"></i>{{Paramètres d'accès au Broker}}</legend>
                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Adresse du broker}}&nbsp;<sup><i class="fa fa-question-circle tooltips" title="{{Paramètres d'accès au Broker.}}"></i></sup></label>
                            <div class="col-lg-7 input-group">
                                <span class="input-group-btn">
                                    <select class="eqLogicAttr form-control roundedLeft tooltips" data-l1key="configuration" data-l2key="mqttProto" style="width:80px;"
                                        title="{{Choisir quel protocole attend le Broker pour la communication.<br/>Pour plus d'information, se référer à la documentation.}}">
                                        <option>mqtt</option>
                                        <option>mqtts</option>
                                        <option>ws</option>
                                        <option>wss</option>
                                    </select>
                                </span>
                                <span class="input-group-addon">://</span>
                                <input class="eqLogicAttr form-control tooltips" data-l1key="configuration" data-l2key="mqttAddress" placeholder="localhost"
                                    title="{{Adresse IP ou nom de domaine du Broker.<br/>Valeur si vide, 'localhost' (donc la machine hébergeant Jeedom).}}">
                                <span class="input-group-addon">:</span>
                                <input class="eqLogicAttr form-control tooltips jmqttPort roundedRight" data-l1key="configuration" data-l2key="mqttPort" type="number" min="1" max="65535" placeholder="port"
                                    title="{{Port réseau sur lequel écoute le Broker.<br/>Valeur si vide, 1883 en mqtt, 8883 en mqtts, 1884 en ws et 8884 en wss.}}">
                                <span class="input-group-addon jmqttWsUrl" style="display:none">/</span>
                                <input class="eqLogicAttr form-control tooltips roundedRight jmqttWsUrl" data-l1key="configuration" data-l2key="mqttWsUrl" style="display:none" placeholder="mqtt"
                                    title="{{URL de la connexion Web Sockets du serveur distant, sans '/' initial.<br/>Valeur si vide, 'mqtt'. Ne pas modifier si vous ne savez pas ce que vous faites.}}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Authentification}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                            title="{{Nom d'utilisateur et Mot de passe permettant de se connecter au Broker.<br/>Remplir ces champs n'est obligatoire que si le Broker est configuré pour.}}"></i></sup></label>
                            <div class="col-lg-7 input-group">
                                <input class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="mqttUser" autocomplete="nope" autofill="off" placeholder="{{Nom d'utilisateur}}" />
                                <span class="input-group-addon">:</span>
                                <input class="eqLogicAttr form-control roundedRight" data-l1key="configuration" data-l2key="mqttPass" type="password" autocomplete="nope" autofill="off" placeholder="{{Mot de passe}}" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-lg-4 control-label">Client-Id&nbsp;<sup><i class="fa fa-question-circle tooltips"
                            title="{{Identifiant avec lequel l’équipement Broker s’inscrit auprès du Broker MQTT.<br/>Il est important que cet identifiant ne soit utilisé QUE par jMQTT sur ce Broker.<br/>Définissez le Client-Id UNIQUEMENT si c'est réellement nécessaire pour vous.}}"></i></sup></label>
                            <div class="col-lg-1">
                                <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttId">
                            </div>
                            <div class="col-lg-6">
                                <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttIdValue" placeholder="jeedom" style="display: none;" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Publier le statut (LWT)}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                            title="{{Active/Désactive la publication du statut (Last Will and Testament) en MQTT sur le Broker.}}"></i></sup></label>
                            <div class="col-lg-1">
                                <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttLwt">
                            </div>
                            <div class="col-lg-6 jmqttLwt" style="display: none;">
                                <input class="eqLogicAttr form-control tooltips" data-l1key="configuration" data-l2key="mqttLwtTopic" placeholder="jeedom/status"
                                title="{{Topic de publication du statut (Last Will and Testament) en MQTT sur ce Broker ('jeedom/status' par défaut).}}">
                            </div>
                        </div>
                        <div class="form-group jmqttLwt" style="display:none">
                            <div class="col-lg-5"></div>
                            <div class="col-lg-6 input-group">
                                <input class="eqLogicAttr form-control tooltips roundedLeft" data-l1key="configuration" data-l2key="mqttLwtOnline" placeholder="online"
                                title="{{Valeur du statut lorsque jMQTT est connecté à ce Broker ('online' par défaut).}}">
                                <span class="input-group-addon">/</span>
                                <input class="eqLogicAttr form-control tooltips roundedRight" data-l1key="configuration" data-l2key="mqttLwtOffline" placeholder="offline"
                                title="{{Valeur du statut lorsque jMQTT est déconnecté de ce Broker ('offline' par défaut).}}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Topic des interactions de Jeedom}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                            title="{{Active/Désactive les interactions au travers du protocole MQTT.<br/>Pour plus d'information, se référer à la documentation.}}"></i></sup></label>
                            <div class="col-lg-1">
                                <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttInt">
                            </div>
                            <div class="col-lg-6 jmqttInt" style="display: none;">
                                <input class="eqLogicAttr form-control tooltips" data-l1key="configuration" data-l2key="mqttIntTopic" placeholder="jeedom/interact"
                                title="{{Topic d'accès aux interactions de Jeedom sur ce Broker ('jeedom/interact' par défaut).<br/>Envoyer la demande en texte directement sur ce topic, elle sera transmise au moteur d'interaction de Jeedom.<br/>La réponse sera envoyée sur le sous-topic '/reply' (donc 'jeedom/interact/reply' par défaut).<br/>Pour des interactions avancées, il est possible de formuler une demande complexe sur le sous-topic '/advanced'.}}">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="col-lg-4 control-label">{{Topic de l'API de Jeedom}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                            title="{{Permet d’accéder à toutes les méthodes de l’API JSON RPC au travers du protocole MQTT.<br/>Pour plus d'information, se référer à la documentation.}}"></i></sup></label>
                            <div class="col-lg-1">
                                <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttApi">
                            </div>
                            <div class="col-lg-6 jmqttApi" style="display: none;">
                                <input class="eqLogicAttr form-control tooltips" data-l1key="configuration" data-l2key="mqttApiTopic" placeholder="jeedom/api"
                                title="{{Topic d'accès à l'API JSON RPC de Jeedom sur ce Broker ('jeedom/status' par défaut).}}">
                            </div>
                        </div>

                        <div class="form-group"><br/></div>
                    </fieldset>
                </div>

                <div class="form-group col-lg-6">
                    <fieldset>
                        <div id="jmqttTls" style="display:none">
                            <legend><i class="fas fa-key"></i>{{Paramètres de Sécurité}}</legend>
                            <div class="form-group">
                                <label class="col-lg-3 control-label">{{Vérifier le certificat}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                                title="{{Vérifie la chaîne d'approbation du certificat présenté par le Broker et que son sujet corresponde à l'IP/Nom de Domaine du Broker.}}"></i></sup></label>
                                <div class="col-lg-9">
                                    <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsCheck">
                                        <option value="public">{{Activé - Autorités Publiques}}</option>
                                        <option value="private">{{Activé - Autorité Personnalisée}}</option>
                                        <option value="disabled">{{Désactivé - Non Recommandé}}</option>
                                    </select>
                                </div>
                            </div>
                            <div id="jmqttTlsCa" class="form-group">
                                <label class="col-lg-3 control-label">{{Autorité Personnalisée}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                                title="{{Autorité de certification attendue pour le Broker.}}"></i></sup></label>
                                <div class="col-lg-1"></div>
                                <div class="col-lg-8 input-group">
                                    <div class="dropzone rounded" style="display:none">{{Déposez ici le certificat CA du Broker}}</div>
                                    <textarea class="eqLogicAttr form-control cert blured roundedLeft" data-l1key="configuration" data-l2key="mqttTlsCa"></textarea>
                                    <span class="btn btn-default btn-file input-group-addon uploadzone roundedRight"><i class="fas fa-upload"></i></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="col-lg-3 control-label">{{Certificat Client}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                                title="{{Certificat Client attendu par le Broker.<br/>Ce Certificat doit être associé à la Clé Privée, dans le champ qui apparaîtra en-dessous, si l'un est fourni l'autre est obligatoire.}}"></i></sup></label>
                                <div class="col-lg-1">
                                    <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="mqttTlsClient">
                                </div>
                                <div class="col-lg-8 input-group jmqttTlsClient" style="display:none">
                                    <div class="dropzone rounded" style="display:none;">{{Déposez ici le certificat publique du client MQTT}}</div>
                                    <textarea class="eqLogicAttr form-control cert blured roundedLeft" data-l1key="configuration" data-l2key="mqttTlsClientCert"></textarea>
                                    <span class="btn btn-default btn-file input-group-addon uploadzone roundedRight"><i class="fas fa-upload"></i></span>
                                </div>
                            </div>
                            <div class="form-group jmqttTlsClient" style="display:none">
                                <label class="col-lg-3 control-label">{{Clé Privée Client}}&nbsp;<sup><i class="fa fa-question-circle tooltips"
                                title="{{Clée Privée du Client permettant de discuter avec le Broker.<br/>Cette Clé Privée doit être associée au Certificat au-dessus, si l'un est fourni l'autre est obligatoire.}}"></i></sup></label>
                                <div class="col-lg-1"></div>
                                <div class="col-lg-8 input-group">
                                    <div class="dropzone rounded" style="display:none">{{Déposez ici la clé privée du client MQTT}}</div>
                                    <textarea class="eqLogicAttr form-control cert blured roundedLeft" data-l1key="configuration" data-l2key="mqttTlsClientKey"></textarea>
                                    <span class="btn btn-default btn-file input-group-addon uploadzone roundedRight"><i class="fas fa-upload"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="form-group"><br/></div>
                    </fieldset>
                </div>

            </form>
        </div>
    </div>
</div>
