<?php

class jMQTTConst {
    const FORCE_DEPENDANCY_INSTALL      = 'forceDepInstall';

    const CLIENT_STATUS                 = 'status';
    const CLIENT_STATUS_ONLINE          = 'online';
    const CLIENT_STATUS_OFFLINE         = 'offline';
    const CLIENT_CONNECTED              = 'connected';

    const CLIENT_OK                     = 'ok';
    const CLIENT_POK                    = 'pok';
    const CLIENT_NOK                    = 'nok';

    const CONF_KEY_TYPE                 = 'type';
    const CONF_KEY_BRK_ID               = 'eqLogic';
    const CONF_KEY_JMQTT_UUID           = 'installUUID';
    const CONF_KEY_MQTT_ADDRESS         = 'mqttAddress';
    const CONF_KEY_MQTT_PORT            = 'mqttPort';
    const CONF_KEY_MQTT_WS_URL          = 'mqttWsUrl';
    const CONF_KEY_MQTT_USER            = 'mqttUser';
    const CONF_KEY_MQTT_PASS            = 'mqttPass';
    const CONF_KEY_MQTT_ID              = 'mqttId';
    const CONF_KEY_MQTT_ID_VALUE        = 'mqttIdValue';
    const CONF_KEY_MQTT_LWT             = 'mqttLwt';
    const CONF_KEY_MQTT_LWT_TOPIC       = 'mqttLwtTopic';
    const CONF_KEY_MQTT_LWT_ONLINE      = 'mqttLwtOnline';
    const CONF_KEY_MQTT_LWT_OFFLINE     = 'mqttLwtOffline';
    const CONF_KEY_MQTT_PROTO           = 'mqttProto';
    const CONF_KEY_MQTT_TLS_CHECK       = 'mqttTlsCheck';
    const CONF_KEY_MQTT_TLS_CA          = 'mqttTlsCa';
    const CONF_KEY_MQTT_TLS_CLI         = 'mqttTlsClient';
    const CONF_KEY_MQTT_TLS_CLI_CERT    = 'mqttTlsClientCert';
    const CONF_KEY_MQTT_TLS_CLI_KEY     = 'mqttTlsClientKey';
    const CONF_KEY_MQTT_INT             = 'mqttInt';
    const CONF_KEY_MQTT_INT_TOPIC       = 'mqttIntTopic';
    const CONF_KEY_MQTT_API             = 'mqttApi';
    const CONF_KEY_MQTT_API_TOPIC       = 'mqttApiTopic';
    const CONF_KEY_QOS                  = 'Qos';
    const CONF_KEY_AUTO_ADD_CMD         = 'auto_add_cmd';
    const CONF_KEY_AUTO_ADD_TOPIC       = 'auto_add_topic';
    const CONF_KEY_BATTERY_CMD          = 'battery_cmd';
    const CONF_KEY_AVAILABILITY_CMD     = 'availability_cmd';
    const CONF_KEY_TEMPLATE_UUID        = 'templateUUID';
    const CONF_KEY_LOGLEVEL             = 'loglevel';

    const CONF_KEY_AUTOPUB              = 'autoPub';
    const CONF_KEY_JSON_PATH            = 'jsonPath';
    const CONF_KEY_PUB_QOS              = 'Qos';
    const CONF_KEY_REQUEST              = 'request';
    const CONF_KEY_RETAIN               = 'retain';

    const CACHE_DAEMON_LAST_SND         = 'daemonLastSnd';
    const CACHE_DAEMON_LAST_RCV         = 'daemonLastRcv';
    const CACHE_DAEMON_PORT             = 'daemonPort';
    const CACHE_DAEMON_UID              = 'daemonUid';
    const CACHE_IGNORE_TOPIC_MISMATCH   = 'ignore_topic_mismatch';
    const CACHE_JMQTT_NEXT_STATS        = 'nextStats';
    const CACHE_LAST_LAUNCH_TIME        = 'lastLaunchTime';
    const CACHE_MQTTCLIENT_CONNECTED    = 'mqttClientConnected';
    const CACHE_REALTIME_MODE           = 'realtime_mode';
    const CACHE_REALTIME_INC_TOPICS     = 'mqttIncTopic';
    const CACHE_REALTIME_EXC_TOPICS     = 'mqttExcTopic';
    const CACHE_REALTIME_RET_TOPICS     = 'mqttRetTopic';
    const CACHE_REALTIME_DURATION       = 'mqttDuration';

    const PATH_BACKUP                   = 'data/backup/';
    const PATH_TEMPLATES_PERSO          = 'data/template/';
    const PATH_TEMPLATES_JMQTT          = 'core/config/template/';

    /**
     * To define a standard jMQTT equipment
     * jMQTT type is either jMQTTConst::TYP_EQPT or jMQTTConst::TYP_BRK.
     * @var string standard jMQTT equipment
     */
    const TYP_EQPT = 'eqpt';

    /**
     * To define a jMQTT broker
     * jMQTT type is either jMQTTConst::TYP_EQPT or jMQTTConst::TYP_BRK.
     * @var string broker jMQTT.
     */
    const TYP_BRK = 'broker';

}
