from os import getpid
from pydantic_settings import BaseSettings


###############################################################################
# Declare default global static settings

pid: str = str(getpid())

rootloglevel: str = 'warning'
logconfig: dict = {
    'version': 1,
    'disable_existing_loggers': False,
    'formatters': {
        'withCallTrace': {'format': '[%(asctime)s]%(levelname)-10s %(threadName)-11s %(name)-15s %(funcName)20s() L%(lineno)-4d %(message)s   call_trace=%(pathname)s L%(lineno)-4d'},
        'withThread': {'format': '[%(asctime)s]%(levelname)-10s %(threadName)-11s %(name)-15s %(funcName)20s() : %(message)s'},
        'withFunction': {'format': '[%(asctime)s]%(levelname)-10s %(name)-20s %(funcName)20s() : %(message)s'},
        'normal': {'format': '[%(asctime)s]%(levelname)-10s %(name)-15s : %(message)s'}
    },
    'filters': {
        'logFilter': {
            '()': 'utils.LogFilter'
        }
    },
    'handlers': {
        'consoleHandler': {
            'class': 'logging.StreamHandler',
            # 'level': 'DEBUG',
            # 'formatter': 'withCallTrace',
            # 'formatter': 'withThread',
            'formatter': 'withFunction',
            # 'formatter': 'normal',
            'stream': 'ext://sys.stdout',
            'filters': [ 'logFilter' ]
        }
    },
    'root': {
        'level': 'DEBUG',
        # 'level': 'WARNING', # TODO disable DEBUG
        'handlers': [ 'consoleHandler' ]
    # },
    # 'loggers': {
    #     'asyncio': {
    #         'level': 'WARNING'
    #     },
    #     'concurrent': {
    #         'level': 'WARNING'
    #     },
    #     'fastapi': {
    #         'level': 'WARNING'
    #     },
    #     'jmqtt': {
    #         'level': 'DEBUG'
    #     },
    #     'uvicorn': {
    #         'level': 'WARNING'
    #     },
    #     'pydantic': {
    #         'level': 'WARNING'
    #     },
    #     'gunicorn': {
    #         'level': 'WARNING'
    #     }
    }
}

timeout_cancel: float = 3.0 # seconds to wait for a task to be canceled


###############################################################################
# Store settings imported from environment, with default values

class JmqttSettings(BaseSettings):
    apikey: str = '!secret'
    callback: str = 'http://localhost/plugins/jMQTT/core/php/callback.php'
    logfile: str = '../../../log/jMQTTd_api'
    loglevel: str = 'warning'
    localonly: bool = True
    pidfile: str = '/tmp/jmqttd.tmp.pid'
    socketport: int = 0


settings = JmqttSettings()
