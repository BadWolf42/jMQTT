import logging, os, queue, requests, socketserver, threading, time
# import AddLogging
# from LogicRegister import *

class JeedomMsg():
	# Bit values of JeedomMsg._status
	KO      = 0
	CAN_SND = 1
	CAN_RCV = 2
	OK      = CAN_SND | CAN_RCV

	# Text Map values of JeedomMsg._status
	_statusToName = {
		KO:      'KO',
		CAN_SND: 'CAN_SND',
		CAN_RCV: 'CAN_RCV',
		OK:      'OK',
	}

	def __init__(self, callback, apikey, port=0):
		self._log         = logging.getLogger('JMsg')
		self._log_rcv     = logging.getLogger('JMsg.Rcv')
		self._log_snd     = logging.getLogger('JMsg.Snd')
		self._callback    = callback
		self._apikey      = apikey
		self._url         = callback+'?apikey='+apikey
		self._status      = self.KO
		self._retry       = 3
		self._socket_port = port
		self._stopworker  = False
		self._socketIn    = None
		self._threadOut   = None
# TODO (nice to have) implement statistics
		self._last_snd    = time.time()
		self._retry_snd   = 0
		self._retry_max   = 5
		self._last_rcv    = time.time()
		self._hb_delay    = 45					# seconds between 2 heartbeat emission
		self._hb_retry    = self._hb_delay / 2	# seconds before retrying
		self._hb_timeout  = self._hb_delay * 3	# seconds before timeout
		self.qFromJ       = queue.Queue()
		self.qToJ         = queue.Queue()
		# self._lock        = threading.Lock()
		socketserver.TCPServer.allow_reuse_address = True

	def is_working(self):
		# TODO (important) Kill daemon if we cannot send for a total of X seconds and/or a total of Y retries "Jeedom is no longer available"
		if self._retry_snd > self._retry_max:
			self._log_snd.error("Nothing has been sent since %ds and after send %d attempts, Jeedom/Apache is probably dead.",
								time.time() - self._last_snd, self._retry_snd)
			return False
		if time.time() - self._last_rcv > self._hb_timeout:
			self._log_rcv.error("Nothing has been received since %ds (max %d), Jeedom is probably dead.",
								time.time() - self._last_rcv, self._hb_timeout)
			return False
		return True

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
					self._log_snd.info('Callback test following Redirection %d -> %s', 4 - redirect, self._url)
				else:
					self._log_snd.info('Callback test following Redirection %d', 4 - redirect)
				return self.send_test(redirect - 1)
			if response.status_code != requests.codes.ok:
				self._log_snd.error('Callback test Error (%s): %s', response.status_code, response.reason)
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
		self._log_snd.debug('Enqued message: %s', msg)
		self.qToJ.put(msg)

	def send(self, msgs):
		i = 1
		while i <= self._retry:
			try:
				r = requests.post(self._url, json=msgs, timeout=(0.5, 120), verify=False) # TODO (low) Check 120s timeout ?!
				if r.status_code == requests.codes.ok:
					self._log_snd.debug('Sent TO Jeedom: %s', msgs)
					self._log_snd.verbose('Received back FROM Jeedom: %s', r.text)
					self._status |= self.CAN_SND
					self._last_snd = time.time()
					self._retry_snd = 0
					return r.text
			except Exception as e:
				self._log_snd.info('Communication issue TO Jeedom (try %d/%d)', i, self._retry)
			i += 1
		self._log_snd.error('COULD NOT send TO Jeedom: %s', msgs)
		self._status &= ~self.CAN_SND
		self._retry_snd += 1

	# def send_non_blocking(self, msg):
		# threading.Thread(target=self.send, args=(msg,), name="SndNoBlk").start()

	def _loopSnd(self):
		self._log_snd.debug("Start")
		max_msg_per_send = 40
		last_hb = 0
		while not self._stopworker:
			if self.qToJ.empty():
				if time.time() - self._last_snd > self._hb_delay:
					if time.time() - last_hb > self._hb_retry: # Avoid sending continuously hb
						self.qToJ.put({"cmd":"hb"}) # Add a heartbeat if it has been too long
						last_hb = time.time()
				else:
					time.sleep(0.1)
				continue # Check if stopworker changed
			msgs = []
			while not self._stopworker and not self.qToJ.empty() and (len(msgs) < max_msg_per_send):
				msg = self.qToJ.get()
				msgs.append(msg)
			self._log_snd.debug("Sending %d msgs", len(msgs))
			self.send(msgs)
			# TODO (important) Put back messages in qToJ if send failed !
		self.qToJ.queue.clear()
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
				raw = self.rfile.read().strip()
				self._log.verbose("Raw data: %s", raw)
				self.server.jmsg.qFromJ.put(raw)
				self.server.jmsg._last_rcv = time.time()
				self._log.verbose("Client [%s:%d] disconnected", *(self.client_address))
		# TODO (low): Implement IPv6 listening, examples:
		#           https://www.bortzmeyer.org/files/echoserver.py
		#           https://www.thecodingforums.com/threads/python-socketserver-with-ipv6.681964/
		#       If so, DaemonUp PID/PORT check may need to be modified
		self._socketIn = socketserver.TCPServer(('127.0.0.1', self._socket_port), SockIn)
		if self._socketIn:
			self._socketIn.jmsg = self
			threading.Thread(target=self._loopRcv, args=(), name="SockIn", daemon=True).start()
			port = self._socketIn.socket.getsockname()[1]
			self._socket_port = port
			self._url = self._callback+'?apikey='+self._apikey+'&uid='+str(os.getpid())+':'+str(port)
			self._log_rcv.info("Started, listening on [%s:%d]", *(self._socketIn.socket.getsockname()))
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
		# self._url = self._callback+'?apikey='+self._apikey     # TODO (low) Check how to send brkDown+daemonDown without uid in url
		self._status &= ~self.CAN_RCV
		self._socketIn = None
		self._log_rcv.debug("Stopped")

