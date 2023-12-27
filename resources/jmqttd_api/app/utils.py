import logging
import logging.config
from socket import socket, AF_INET6, IPPROTO_IPV6, IPV6_V6ONLY, SO_REUSEADDR, SOL_SOCKET

from fastapi import HTTPException, Security, status
from fastapi.security import HTTPBearer

from settings import settings, logconfig


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
        'trace':    logging.TRACE,
        'debug':    logging.DEBUG,
        'info':     logging.INFO,
        'notice':   logging.NOTICE,
        'warning':  logging.WARNING,
        'error':    logging.ERROR,
        'critical': logging.CRITICAL,
        'none':     logging.ERROR,
        'notset':   logging.NOTSET
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
# Build a reusable socket listening in IPv4 and IPv6
def getSocket():
    sock = socket(AF_INET6)
    sock.setsockopt(IPPROTO_IPV6, IPV6_V6ONLY, False)
    sock.setsockopt(SOL_SOCKET, SO_REUSEADDR, True)

    host = '::1' if settings.localonly else '::'
    try:
        sock.bind((host, settings.socketport))
    except OSError:
        return None

    return sock


# -----------------------------------------------------------------------------
def getToken(token: str = Security(HTTPBearer())):
    if token.credentials == settings.apikey:
        return token
    raise HTTPException(status_code=status.HTTP_403_FORBIDDEN, detail="Invalid API key")
