# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import json
import logging
import os
import signal
import sys
import threading
import traceback
import time

from AddLogging import add_new_loglevels
from JeedomMsg import JeedomMsg
from jMqttClient import jMqttClient


# Takes a message "msg" and an array of (key, mandatory, default_val, expected_type) tupples to validate
#   - "key" is an orderable and printable value representing a slot in msg (msg[key])
#   - "mandatory" is a bool stating if msg[key] is mandatory or just expected
#   - "default_val" can by any value to be placed in msg[key] if missing
#         if "default_val" is None and mandatory is True, an error is logged if key is missing
#   - "expected_type" is the expected type of msg[key], or None if no type is expected
#         if msg[key] is not of expected type and cast atempt fails, an error is printed
#   The return value is False if an error occurs, True otherwise
def validate_params(msg, constraints):
    res = True
    for (key, mandatory, default_val, expected_type) in constraints:
        if key not in msg or msg[key] == '':
            if mandatory:
                if default_val is None:
                    logging.error(
                        'Cmd "%s" is missing parameter "%s" dump=%s',
                        msg['cmd'],
                        key,
                        json.dumps(msg)
                    )
                    res = False
                else:
                    msg[key] = default_val
        elif (expected_type is not None) and (not isinstance(msg[key], expected_type)):
            try:
                if expected_type is bool:
                    msg[key] = str(msg[key]).lower() in ['true', '1']
                else:
                    msg[key] = expected_type(msg[key])
            except Exception:
                logging.error(
                    'Cmd "%s" has incorrect parameter "%s" (is %s, should be %s) dump=%s',
                    msg['cmd'],
                    key,
                    type(msg[key]),
                    expected_type,
                    json.dumps(msg)
                )
                res = False
    return res

# ----------------------------------------------------------------------------


class Main():
    def __init__(self, flag):
        # Values from ENV with defaults
        self._log_level = os.getenv("LOGLEVEL", "error")
        self._socket_port = int(os.getenv("SOCKETPORT", 0))
        self._pidfile = os.getenv("PIDFILE", '/tmp/jmqttd.pid')
        self._callback = os.getenv("CALLBACK", None)
        self._apikey = os.getenv("APIKEY", None)

        # Class logger
        add_new_loglevels()
        self.log = logging.getLogger('Main')

        # Handle Run & Shutdown
        self.should_stop = flag
        self.has_stopped = threading.Event()
        self.has_stopped.set()

        # Tables for the meal
        self.message_map = {
            'newMqttClient': self.h_newClient,
            'removeMqttClient': self.h_delClient,
            'subscribeTopic': self.h_subTopic,
            'unsubscribeTopic': self.h_unsubTopic,
            'messageOut': self.h_messageOut,
            'realTimeStart': self.h_realTimeStart,
            'realTimeStop': self.h_realTimeStop,
            'realTimeClear': self.h_realTimeClear,
            'changeApiKey': self.h_changeApiKey,
            'loglevel': self.h_logLevel
        }
        self.jmqttclients = {}
        self.jcom = None

    def set_log_level(self, level='error'):
        newlevel = {
            'verbose':  logging.VERBOSE,
            'debug':    logging.DEBUG,
            'info':     logging.INFO,
            'warning':  logging.WARNING,
            'error':    logging.ERROR,
            'critical': logging.CRITICAL
        }.get(level, logging.NONE)
        logging.getLogger().setLevel(logging.INFO)
        self.log.info('New log level set to: %s', logging.getLevelName(newlevel))
        logging.getLogger().setLevel(newlevel)
        debuglevel = (newlevel <= logging.VERBOSE)
        # HTTPConnection.debuglevel = int(debuglevel)
        requests_log = logging.getLogger("requests.packages.urllib3")
        pool_log = logging.getLogger("urllib3.connectionpool")
        if debuglevel:
            # self.log.verbose('Debug log active for requests & urllib3')
            requests_log.setLevel(logging.DEBUG)
            pool_log.setLevel(logging.DEBUG)
        else:
            requests_log.setLevel(logging.WARNING)
            pool_log.setLevel(logging.WARNING)
        requests_log.propagate = debuglevel
        pool_log.propagate = debuglevel

    def prepare(self):
        # Callback is mandatory
        if self._callback is None:
            self.log.critical('Missing callback url (use ENV var CALLBACK="<url>")')
            sys.exit(2)

        # Apikey is mandatory
        if self._apikey is None:
            self.log.critical('Missing API key (use ENV var APIKEY=<key>)')
            sys.exit(2)

        # Set the global logging level
        self.set_log_level(self._log_level)

        # Check the PID file
        if os.path.isfile(self._pidfile):
            self.log.debug('PID File "%s" already exists.', self._pidfile)
            with open(self._pidfile, "r") as f:
                f.seek(0)
                pid = int(f.readline())
            try:
                # Try to ping the pid
                os.kill(pid, 0)
            except OSError:  # PID does not run we can continue
                pass
            except Exception:  # just in case
                self.log.exception("Unexpected error when checking PID")
                sys.exit(3)
            else:  # PID is alive -> we die
                self.log.error('This daemon already runs! Exit 0')
                sys.exit(0)
        try:
            # Try to write PID to file
            pid = str(os.getpid())
            self.log.debug("Writing PID %s to %s", pid, self._pidfile)
            with open(self._pidfile, 'w') as f:
                f.write("%s\n" % pid)
        except Exception:
            self.log.exception('Could not write PID file')
            sys.exit(4)

        # All set, ready to run
        self.log.info('Log level   : %s', self._log_level)
        self.log.info('Socket port : %s', self._socket_port)
        self.log.info('Callback url: %s', self._callback)
        self.log.info('PID file    : %s', self._pidfile)
        self.log.debug('Apikey      : %s', self._apikey)

    def open_comm(self):
        self.jcom = JeedomMsg(self._callback, self._apikey, self._socket_port)
        try:  # Create communication channel to get instructions FROM Jeedom
            self.jcom.receiver_start()
        except Exception:
            if self.log.isEnabledFor(logging.DEBUG):
                self.log.exception(
                    'Failed to Open the communication channel to get instructions FROM Jeedom'
                )
            else:
                self.log.critical(
                    'Failed to Open the communication channel to get instructions FROM Jeedom'
                )
            return False
        if self.jcom.send_test():  # Test communication channel TO Jeedom
            self.jcom.sender_start()  # Start sender
            data = self.jcom.send([{"cmd": "daemonUp"}])  # Must use send to be synchronous and get a reply
            # self.log.info('Successfully informed Jeedom')
            self.log.debug(
                'Open Comm   : Sent Daemon Up signal to Jeedom, got data: "%s"',
                data
            )
        else:
            self.log.critical(
                'Open Comm   : Failed to Open the communication channel to send informations back TO Jeedom'
            )
            self.jcom.receiver_stop()
            self.log.critical(
                'Open Comm   : Closed the communication channel to get instructions FROM Jeedom'
            )
            self.jcom = None
            return False
        return True

    def run(self):
        self.has_stopped.clear()
        # Wait for instructions
        while not self.should_stop.is_set():
            if not self.jcom.is_working():  # Check if there has been bidirectional communication with Jeedom
                self.should_stop.set()
            if len(self.jcom.qFromJ) == 0:  # faster that Exception handling
                time.sleep(0.1)
                continue  # Check if should_stop changed

            # Get some new raw data and cook it
            try:
                jeedom_raw = self.jcom.qFromJ.pop()
                jeedom_msg = jeedom_raw.decode('utf-8')
                # self.log.debug('Received from Jeedom: %s', jeedom_msg)
                message = json.loads(jeedom_msg)
            except IndexError:
                continue  # More chance next time
            except Exception:
                if self.log.isEnabledFor(logging.DEBUG):
                    self.log.exception('Unable to get a message or decode JSON')
                continue  # Let's retry
            # Check API key
            if 'apikey' not in message or message['apikey'] != self._apikey:
                self.log.error('Invalid apikey from socket : %s', message)
                continue  # Ignore unauthorized messages

            # Check for there is a cmd in message
            if 'cmd' not in message or not isinstance(message['cmd'], str) or message['cmd'] == '':
                self.log.error('Bad cmd parameter in message dump=%s', json.dumps(message))
                continue

            if message['cmd'] == 'hb':
                self.log.debug('Heartbeat received from Jeedom')
                continue

            # Check for mandatory parameters before handling the message
            #                                  key, mandatory, default_val, expected_type
            if not validate_params(message, [['id',      True,        None, str]]):
                continue

            # Register the call
            try:
                self.message_map.get(message['cmd'], self.h_unknown)(message)
            except Exception:
                self.log.exception('Message FROM Jeedom raised Exception')
        self.has_stopped.set()

    def h_newClient(self, message):
        # Check for                      key, mandatory, default_val, expected_type
        if not validate_params(message, [['hostname',      True,        None, str],
                                         ['port',         False,        None, int],
                                         ['tls',           True,       False, bool]]):
            return

        if message['tls']:
            if 'tlscafile' not in message or message['tlscafile'] == '':
                message['tlscafile'] = None
            elif not os.access(message['tlscafile'], os.R_OK):
                self.log.warning(
                    'Unable to read CA file "%s" for Broker %s',
                    message['tlscafile'],
                    message['id']
                )
                return
            if 'tlsclicertfile' not in message or message['tlsclicertfile'] == '':
                message['tlsclicertfile'] = None
            elif not os.access(message['tlsclicertfile'], os.R_OK):
                self.log.warning(
                    'Unable to read Client Certificate file "%s" for Broker %s',
                    message['tlsclicertfile'],
                    message['id']
                )
                return
            if 'tlsclikeyfile' not in message or message['tlsclikeyfile'] == '':
                message['tlsclikeyfile'] = None
            elif not os.access(message['tlsclikeyfile'], os.R_OK):
                self.log.warning(
                    'Unable to read Client Key file "%s" for Broker %s',
                    message['tlsclikeyfile'],
                    message['id']
                )
                return
            if message['tlsclicertfile'] is None and message['tlsclikeyfile'] is not None:
                self.log.warning(
                    'Client Certificate is defined but Client Key is NOT for Broker %s',
                    message['id']
                )
                return
            if message['tlsclicertfile'] is not None and message['tlsclikeyfile'] is None:
                self.log.warning(
                    'Client Key is defined but Client Certificate is NOT for Broker %s',
                    message['id']
                    )
                return
        # if jmqttclient already exists then restart it
        if message['id'] in self.jmqttclients:
            self.log.info('Client already exists for Broker %s. Restarting it.', message['id'])
            self.jmqttclients[message['id']].restart(message)
        else:  # create requested jmqttclient
            self.log.info('Creating Client for Broker %s.', message['id'])
            newjMqttClient = jMqttClient(self.jcom, message)
            newjMqttClient.start()
            self.jmqttclients[message['id']] = newjMqttClient

    def h_delClient(self, message):
        # if jmqttclient exists then remove it
        if message['id'] in self.jmqttclients:
            self.log.info('Starting removal of Client for Broker %s', message['id'])
            self.jmqttclients[message['id']].stop()
            del self.jmqttclients[message['id']]
        else:
            self.log.info('No client found for Broker %s', message['id'])

    def h_subTopic(self, message):
        # Check for                     key, mandatory, default_val, expected_type
        if not validate_params(message, [['topic', True, None, str],
                                         ['qos', True, None, int]]):
            return
        if message['id'] in self.jmqttclients:
            self.jmqttclients[message['id']].subscribe_topic(message['topic'], message['qos'])
        else:
            self.log.debug('No client found for Broker %s', message['id'])

    def h_realTimeStart(self, message):
        # Check for                     key, mandatory, default_val, expected_type
        if not validate_params(message, [['file', True, None, str],
                                         ['subscribe', True, [], list],
                                         ['exclude', True, [], list],
                                         ['retained', True, False, bool],
                                         ['duration', True, 180, int]]):
            return
        if message['id'] in self.jmqttclients:
            self.jmqttclients[message['id']].realtime_start(
                message['file'],
                message['subscribe'],
                message['exclude'],
                message['retained'],
                message['duration']
            )
        else:
            self.log.debug('No client found for Broker %s', message['id'])

    def h_realTimeStop(self, message):
        if message['id'] in self.jmqttclients:
            self.jmqttclients[message['id']].realtime_stop()
        else:
            self.log.debug('No client found for Broker %s', message['id'])

    def h_realTimeClear(self, message):
        # Check for                     key, mandatory, default_val, expected_type
        if not validate_params(message, [['file', True, None, str]]):
            return
        if message['id'] in self.jmqttclients:
            self.jmqttclients[message['id']].realtime_clear(message['file'])
        else:
            self.log.debug('No client found for Broker %s', message['id'])

    def h_unsubTopic(self, message):
        # Check for                     key, mandatory, default_val, expected_type
        if not validate_params(message, [['topic',      True,        None, str]]):
            return
        if message['id'] in self.jmqttclients:
            self.jmqttclients[message['id']].unsubscribe_topic(message['topic'])
        else:
            self.log.debug('No client found for Broker %s', message['id'])

    def h_messageOut(self, message):
        # Check for                   key, mandatory, default_val, expected_type
        if not validate_params(message, [['topic',      True,        None, str],
                                         ['payload',    True,          '', str],
                                         ['qos',        True,        None, int],
                                         ['retain',     True,        None, bool]]):
            return
        # Supplementary test on qos
        if message['qos'] < 0 or message['qos'] > 2:
            self.log.error('Wrong value for qos "%d" for Broker %s', message['qos'], message['id'])
            return
        if message['id'] in self.jmqttclients:
            self.jmqttclients[message['id']].publish(
                message['topic'],
                message['payload'],
                message['qos'],
                message['retain']
            )
        else:
            self.log.debug('No client found for Broker %s', message['id'])

    def h_changeApiKey(self, message):
        # Check for                   key, mandatory, default_val, expected_type
        if not validate_params(message, [['newApiKey',  True,        None, str]]):
            return
        self.log.debug('Change APIKEY from %s to %s', self._apikey, message['newApiKey'])
        self._apikey = message['newApiKey']
        self.jcom.set_apikey(self._apikey)

    def h_logLevel(self, message):
        self.set_log_level(message['level'])

    def h_unknown(self, message):
        # Message when Cmd is not found
        self.log.debug('Unknown cmd "%16s" dump="%s"', message['cmd'], json.dumps(message))

    def shutdown(self):
        self.log.info('Stop jMQTT python daemon')
        self.should_stop.set()
        self.has_stopped.wait(timeout=4)

        # Close the open communication channel for Jeedom
        try:
            if self.jcom is not None:
                self.jcom.receiver_stop()
        except Exception:
            if self.log.isEnabledFor(logging.DEBUG):
                self.log.debug("Failed to close Socket for Jeedom")

        # Stop all the Clients
        try:
            for id in list(self.jmqttclients):
                self.jmqttclients[id].stop()
        except Exception:
            if self.log.isEnabledFor(logging.DEBUG):
                self.log.exception('Clients Stop Exception')

        # Kill all the Clients
        try:
            for id in list(self.jmqttclients):
                del self.jmqttclients[id]
        except Exception:
            if self.log.isEnabledFor(logging.DEBUG):
                self.log.exception('Clients Kill Exception')

        # If possible Send daemon Down signal and stop sender
        if self.jcom is not None:
            self.log.debug('Sent Daemon Down signal to Jeedom')
            # self.jcom.send_async({"cmd":"daemonDown"})
            self.jcom.sender_stop()
            self.jcom.send([{"cmd": "daemonDown"}])

# ----------------------------------------------------------------------------


if __name__ == '__main__':
    # Formater for the output of the logger
    # formatter = logging.Formatter('[%(asctime)s][%(levelname)s] %(message)s')
    formatter = logging.Formatter(
        '[%(asctime)s][%(levelname)s] %(name)-15s %(threadName)-10s %(funcName)15s() : %(message)s'
    )

    # STDOUT will get all logs
    ch = logging.StreamHandler()
    ch.setLevel(logging.DEBUG)
    ch.setFormatter(formatter)

    # Attach the handler to the main logger
    logger = logging.getLogger()
    logger.handlers = []
    logger.addHandler(ch)

    # Get an instance of Main
    should_stop = threading.Event()
    should_stop.clear()
    m = Main(should_stop)

    def dumpstacks(signal, frame):
        id2name = dict([(th.ident, th.name) for th in threading.enumerate()])
        code = ['A dump of Daemon Stack has been requested:']
        for threadId, stack in sys._current_frames().items():
            code.append("\n# Thread: %s(%d)" % (id2name.get(threadId, ""), threadId))
            for filename, lineno, name, line in traceback.extract_stack(stack):
                code.append('File: "%s", line %d, in %s' % (filename, lineno, name))
                if line:
                    code.append("  %s" % (line.strip()))
        logging.critical("\n".join(code))
    signal.signal(signal.SIGUSR1, dumpstacks)

    # Interrupt handler
    def signal_handler(signum=None, frame=None):
        logging.debug("Signal %d caught, exiting...", signum)
        should_stop.set()

    # Connect the signals to the handler
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    # Ready ? Let's do something now
    m.prepare()
    if m.open_comm():
        m.run()
    m.shutdown()

    # Always exit well
    logger.debug("Exit 0")
    sys.stdout.flush()
    sys.exit(0)
