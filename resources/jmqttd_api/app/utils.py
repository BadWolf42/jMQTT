import logging
import logging.config
from json import load
from os import kill, remove
from os.path import isfile, dirname, realpath
from platform import python_version, version, system
from socket import socket, AF_INET, SO_REUSEADDR, SOL_SOCKET
from sys import exit

from settings import pid, settings, logconfig

from callbacks import Callbacks
from healthcheck import Healthcheck
from logics.logic import Logic
from logics.broker import BrkLogic


logger = logging.getLogger('jmqtt.utils')


# -----------------------------------------------------------------------------
class LogFilter(logging.Filter):
    def filter(self, record):
        record.levelname = '[' + record.levelname + ']'
        if record.threadName == 'AnyIO worker thread':
            record.threadName = 'AnyIO'
        return True


# -----------------------------------------------------------------------------
def setupLoggers(logfile: str):
    # Add 3 new levels to logging module
    logging.TRACE = logging.DEBUG - 5
    logging.NOTICE = logging.INFO + 5
    logging.NONE = logging.CRITICAL + 5

    logging.addLevelName(logging.TRACE, "TRACE")
    logging.addLevelName(logging.NOTICE, "NOTICE")
    logging.addLevelName(logging.NONE, "NONE")

    def trace(self, message, *args, **kws):
        if self.isEnabledFor(logging.TRACE):
            self._log(logging.TRACE, message, args, **kws)

    logging.Logger.trace = trace

    def notice(self, message, *args, **kws):
        if self.isEnabledFor(logging.NOTICE):
            self._log(logging.NOTICE, message, args, **kws)

    logging.Logger.notice = notice

    # Load logging configuration
    logconfig['handlers']['fileHandler']['filename'] = logfile
    logging.config.dictConfig(logconfig)


# -----------------------------------------------------------------------------
def setLevel(level, _logger=''):
    newlevel = {
        'trace': logging.TRACE,
        'debug': logging.DEBUG,
        'info': logging.INFO,
        'notice': logging.NOTICE,
        'warning': logging.WARNING,
        'error': logging.ERROR,
        'critical': logging.CRITICAL,
        'none': logging.ERROR,
        'notset': logging.NOTSET
    }.get(level, logging.ERROR)
    logging.getLogger(_logger).setLevel(newlevel)
    return newlevel


# -----------------------------------------------------------------------------
def dumpLoggers():
    return {
        name: logging.getLevelName(logging.getLogger(name).getEffectiveLevel())
        for name in [''] + sorted(logging.root.manager.loggerDict)
    }


# -----------------------------------------------------------------------------
# Build a reusable socket listening in IPv4
def getSocket():
    sock = socket(AF_INET)
    sock.setsockopt(SOL_SOCKET, SO_REUSEADDR, True)

    host = '127.0.0.1' if settings.localonly else '0.0.0.0'
    try:
        sock.bind((host, settings.socketport))
    except OSError:
        return None

    return sock


# -----------------------------------------------------------------------------
def setup():
    # Add trace, notice, none loglevels and logfilename
    setupLoggers(settings.logfile)
    # Get loglevel from ENV
    setLevel(settings.loglevel, 'jmqtt')

    # Welcome message
    logger.debug('Python v%s on %s %s', python_version(), system(), version())
    with open(
        dirname(realpath(__file__)) + '/../../../plugin_info/info.json'
    ) as json_file:
        logger.info(
            '❤ Thanks for using jMQTT v%s ❤', load(json_file)['pluginVersion']
        )

    # Display loggers informations if logging at least in DEBUG
    if logger.isEnabledFor(logging.DEBUG):
        logger.debug('┌─► Loggers ◄────────────────────────────')
        for name, level in dumpLoggers().items():
            logger.debug('│ %-30s%s', name, level)
        logger.debug('└────────────────────────────────────────')

    # Check the PID file
    if isfile(settings.pidfile):
        logger.debug('PID File "%s" already exists.', settings.pidfile)
        with open(settings.pidfile, "r") as f:
            f.seek(0)
            fpid = int(f.readline())
        try:
            # Try to ping the pid
            kill(fpid, 0)
        except OSError:
            # PID does not run we can continue
            pass
        except Exception:
            # just in case
            logger.exception("Unexpected error when checking PID")
            exit(3)
        else:
            # PID is alive -> we die
            logger.error('A jMQTT daemon already running! Exit 0')
            exit(0)
    try:
        # Try to write PID to file
        logger.debug("Writing PID %s to %s", pid, settings.pidfile)
        with open(settings.pidfile, 'w') as f:
            f.write("%s\n" % pid)
    except Exception:
        logger.exception('Could not write PID file')
        exit(4)


# -----------------------------------------------------------------------------
async def startup():
    logger.info('jMQTTd is starting...')

    # Display daemon informations
    logger.info('┌─► Daemon ◄─────────────────────────────')
    if settings.localonly:
        logger.debug('│ Listening   : on localhost only (doc disabled)')
    else:
        logger.debug('│ Listening   : on all interfaces (doc enabled)')
    # if dynamic port, socketport is only available after setup
    logger.info('│ Socket port : %s', settings.socketport)
    logger.info('│ Log file    : %s', settings.logfile)
    logger.info('│ Log level   : %s', settings.loglevel)
    logger.info('│ Callback url: %s', settings.callback)
    logger.debug('│ PID file    : %s', settings.pidfile)
    logger.debug('│ Apikey      : %s', settings.apikey)
    logger.info('└────────────────────────────────────────')

    # Test communication channel TO Jeedom
    if await Callbacks.test():
        logger.info('Communication channel with Jeedom is available')
    else:
        logger.critical(
            'Failed to Open the communication channel '
            'to get instructions FROM Jeedom'
        )
        raise Exception('Could not communicate with Jeedom')

    logger.info('jMQTTd is started')


# -----------------------------------------------------------------------------
async def shutdown():
    logger.info('jMQTTd is stopping...')

    # TODO remove debug
    await Logic.printTree()

    # logger.debug('Running tasks:\n%s', asyncio.all_tasks())
    await Healthcheck.stop()

    # Stop all register BrkLogic
    for inst in BrkLogic.all.values():
        await inst.stop()

    # Inform Jeedom that daemon is going offline
    await Callbacks.daemonDown()

    # Delete PID file
    if isfile(settings.pidfile):
        logger.debug("Removing PID file %s", settings.pidfile)
        remove(settings.pidfile)

    logger.info('jMQTTd is stopped')
