from os import getpid
from pydantic_settings import BaseSettings


###############################################################################
# Declare default global static settings

pid: str = str(getpid())

logconfig: str = 'logging.yaml'
rootloglevel: str = 'warning'

timeout_cancel: float = 3.0 # seconds to wait for a task to be canceled


###############################################################################
# Store settings imported from environment, with default values

class JmqttSettings(BaseSettings):
    apikey: str = '!secret'
    callback: str = 'http://localhost/plugins/jMQTT/core/php/stub.php'
    lofile: str = '../../../log/jMQTTd_api'
    loglevel: str = 'warning'
    localonly: bool = True
    pidfile: str = '/tmp/jmqttd.tmp.pid'
    socketport: int = 0


settings = JmqttSettings()
