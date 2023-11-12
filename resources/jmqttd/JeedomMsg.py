import logging
import os
import requests
import socketserver
import threading
import time
from collections import deque


class JeedomMsg():
    # Bit values of JeedomMsg._status
    KO = 0
    CAN_SND = 1
    CAN_RCV = 2
    OK = CAN_SND | CAN_RCV

    # Text Map values of JeedomMsg._status
    _statusToName = {
        KO: 'KO',
        CAN_SND: 'CAN_SND',
        CAN_RCV: 'CAN_RCV',
        OK: 'OK',
    }

    def __init__(self, callback, apikey, port=0):
        self._log = logging.getLogger('JMsg')
        self._log_rcv = logging.getLogger('JMsg.Rcv')
        self._log_snd = logging.getLogger('JMsg.Snd')
        self._callback = callback
        self._apikey = apikey
        self._url = callback+'?apikey='+apikey
        self._status = self.KO
        self._retry = 3
        self._socket_port = port
        self._stopworker = False
        self._socketIn = None
        self._threadOut = None
        self._last_snd = time.time()           # time of the last snd msg
        self._retry_snd = 0                    # number of send retries
        self._retry_max = 5                    # max number of send retries
        self._snd_timeout = 135                # seconds before send timeout
        self._last_rcv = time.time()           # time of the last rcv msg
        self._hb_delay = 45                    # seconds between 2 heartbeat emission
        self._hb_retry = self._hb_delay / 2    # seconds before retrying
        self._hb_timeout = self._hb_delay * 7  # seconds before timeout
        self.qFromJ = deque()
        self.qToJ = deque()
        # self._lock = threading.Lock()
        socketserver.TCPServer.allow_reuse_address = True

    def is_working(self):
        # Kill daemon if we cannot send for a total of X seconds and/or a total of Y retries "Jeedom is no longer available"
        if time.time() - self._last_snd > self._snd_timeout and self._retry_snd > self._retry_max:
            self._log_snd.error(
                "Nothing could sent for %ds (max %ds) AND after %d attempts (max %d), Jeedom/Apache is probably dead.",
                time.time() - self._last_snd,
                self._snd_timeout,
                self._retry_snd,
                self._retry_max
            )
            return False
        if time.time() - self._last_rcv > self._hb_timeout:
            self._log_rcv.error(
                "Nothing has been received for %ds (max %ds), Jeedom does not want me any longer.",
                time.time() - self._last_rcv,
                self._hb_timeout
            )
            return False
        return True

    def set_apikey(self, apikey):
        self._apikey = apikey
        self._url = self._callback + '?apikey=' + self._apikey
        self._url += '&uid=' + str(os.getpid()) + ':' + str(self._socket_port)
        self.send_test()

    def get_status(self):
        return self._statusToName.get(self._status, self._statusToName[self.KO])

    def send_test(self, redirect=3):
        try:
            response = requests.get(self._url, timeout=3., allow_redirects=False, verify=False)
            if response.is_redirect:
                if redirect == 0:
                    self._log_snd.error('Callback test following Too Many Redirections')
                    return False
                self._url = response.headers['Location']
                if self._log_snd.isEnabledFor(logging.DEBUG):
                    self._log_snd.info(
                        'Callback test following Redirection %d -> %s',
                        4 - redirect,
                        self._url
                    )
                else:
                    self._log_snd.info(
                        'Callback test following Redirection %d',
                        4 - redirect
                    )
                return self.send_test(redirect - 1)
            if response.status_code != requests.codes.ok:
                self._log_snd.error(
                    'Callback test Error (%s): %s',
                    response.status_code,
                    response.reason
                )
                self._status &= ~self.CAN_SND
                self._retry_snd += 1
                return False
        except Exception as e:
            if self._log_snd.isEnabledFor(logging.DEBUG):
                self._log_snd.exception('Callback test Exception')
            else:
                self._log_snd.error('Callback test Exception (%s)', e)
            self._status &= ~self.CAN_SND
            self._retry_snd += 1
            return False
        self._log_snd.debug('Test successful')
        self._status |= self.CAN_SND
        self._last_snd = time.time()
        self._retry_snd = 0
        return True

    def send_async(self, msg):
        try:
            t = time.time()
            self.qToJ.appendleft(msg)
            self._log_snd.debug(
                'Enqued the message in %fms (qToJ size %d): %s',
                (time.time() - t)*1000,
                len(self.qToJ),
                msg
            )
        except Exception:
            self._log_snd.exception('Exception while enquing in qToJ')

    def send(self, msgs, retry=True):
        i = 1
        while i <= self._retry or (not retry and i == 1):
            try:
                t = time.time()
                # TODO: Check if requests.post timeout of 0.5/120s is ok
                #  labels: quality, python
                r = requests.post(self._url, json=msgs, timeout=(0.5, 120), verify=False)
                if r.status_code == requests.codes.ok:
                    self._log_snd.debug(
                        'Sent TO Jeedom %d messages handled in %fms (qToJ size %d): %s',
                        len(msgs),
                        (time.time() - t)*1000,
                        len(self.qToJ),
                        msgs
                    )
                    self._log_snd.verbose('Received back FROM Jeedom: %s', r.text)
                    self._status |= self.CAN_SND
                    self._last_snd = time.time()
                    self._retry_snd = 0
                    return r.text
            except Exception:
                if retry:
                    self._log_snd.info('Communication issue TO Jeedom (try %d/%d)', i, self._retry)
            i += 1
        self._log_snd.error('COULD NOT send TO Jeedom: %s', msgs)
        self._status &= ~self.CAN_SND
        self._retry_snd += 1
        return None

    # def send_non_blocking(self, msg):
        # threading.Thread(target=self.send, args=(msg,), name="SndNoBlk").start()

    def _loopSnd(self):
        self._log_snd.debug("Start")
        max_msg_per_send = 40
        last_hb = 0
        while not self._stopworker:
            if len(self.qToJ) == 0:
                if time.time() - self._last_snd > self._hb_delay:
                    if time.time() - last_hb > self._hb_retry:  # Avoid sending continuously hb
                        # Send the heartbeat asynchronously to avoid congestion (lots of messages in qToJ)
                        threading.Thread(
                            target=self.send,
                            args=([{"cmd": "hb"}], False),
                            name="SndNoBlkHb",
                            daemon=True
                        ).start()
                        self._log_snd.debug(
                            "Sending a heartbeat to Jeedom, nothing sent since %ds (max %ds)",
                            time.time() - self._last_snd,
                            self._hb_delay
                        )
                        last_hb = time.time()
                else:
                    time.sleep(0.1)
                continue  # Check if stopworker changed
            msgs = []
            while not self._stopworker and not len(self.qToJ) == 0 and (len(msgs) < max_msg_per_send):
                msg = self.qToJ.pop()
                msgs.append(msg)
            self._log_snd.debug("Sending %d messages (%d left in queue)", len(msgs), len(self.qToJ))
            if self.send(msgs) is None:
                pass
                # self.qToJ.extend(msgs) # Put back messages in qToJ if send failed !
        self.qToJ.clear()
        self._log_snd.info("Stopped")

    def sender_start(self):
        if self._threadOut is None:
            self._log_snd.debug('Start requested')
            self._stopworker = False
            self._threadOut = threading.Thread(target=self._loopSnd, name="SockOut")
            self._threadOut.start()
            self._log_snd.info('Started')
        else:
            self._log_snd.debug('Already Started')

    def sender_stop(self):
        if self._stopworker:
            self._log_snd.debug('Stop already requested')
            return
        self._log_snd.debug('Stop requested')
        self._stopworker = True
        if self._threadOut is not None and self._threadOut.is_alive():
            self._threadOut.join(1.0)
        self._threadOut = None
        self._log_snd.debug("Stopped")

    def _loopRcv(self):
        self._log_rcv.debug("Start")
        self._socketIn.serve_forever()
        self._status &= ~self.CAN_RCV
        self._log_rcv.info("Stopped")

    def receiver_start(self):
        self._log_rcv.debug('Start requested')
        if self._socketIn is not None:
            self._status &= ~self.CAN_RCV
            raise RuntimeError("Socket interface already started")

        class SockIn(socketserver.StreamRequestHandler):
            def setup(self):
                socketserver.StreamRequestHandler.setup(self)
                self._log = logging.getLogger("JMsg.Rcv.Sock")

            def handle(self):
                self._log.verbose("Client [%s:%d] connected", *(self.client_address))
                try:
                    raw = self.rfile.read().strip()
                    self._log.verbose("Raw data: %s", raw)
                    self.server.jmsg.qFromJ.appendleft(raw)
                    self.server.jmsg._last_rcv = time.time()
                except Exception:
                    self._log.exception('Exception while enquing in qFromJ')
                # self._log.debug('qFromJ size: %d', len(self.server.jmsg.qFromJ))
                self._log.verbose("Client [%s:%d] disconnected", *(self.client_address))

        self._socketIn = socketserver.TCPServer(('127.0.0.1', self._socket_port), SockIn)
        if self._socketIn:
            self._socketIn.jmsg = self
            threading.Thread(target=self._loopRcv, args=(), name="SockIn", daemon=True).start()
            self._socket_port = self._socketIn.socket.getsockname()[1]
            self._url = self._callback + '?apikey=' + self._apikey
            self._url += '&uid=' + str(os.getpid()) + ':' + str(self._socket_port)
            self._log_rcv.info(
                "Started, listening on [%s:%d]",
                *(self._socketIn.socket.getsockname())
            )
            self._status |= self.CAN_RCV
        else:
            self._log_rcv.critical("Cannot open socket interface")
            self._status &= ~self.CAN_RCV
            raise IOError("Cannot open socket interface")

    def receiver_port(self):
        return self._socket_port

    def receiver_stop(self):
        self._log_rcv.debug('Stop requested')
        if self._socketIn is None:
            return
        self._socketIn.shutdown()
        self._status &= ~self.CAN_RCV
        self._socketIn = None
        self._log_rcv.debug("Stopped")
