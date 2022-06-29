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
		self._socket_host = '127.0.0.1'
		self._socket_port = port
		self._stopworker  = False
		self._socketIn    = None
		self._threadOut   = None
		#TODO implement statistics
		self._last_snd    = time.time()
		self._retry_snd   = 0
		self._retry_max   = 5
		self._last_rcv    = time.time()
		self._hb_delay    = 45 # seconds
		self.qFromJ       = queue.Queue()
		self.qToJ         = queue.Queue()
		# self._lock        = threading.Lock()
		socketserver.TCPServer.allow_reuse_address = True

	def is_working(self):
		# TODO Kill daemon if we cannot send for a total of X seconds and/or a total of Y retries "Jeedom is no longer available"
		if self._retry_snd > self._retry_max:
			self._log_snd.waring("Nothing has been sent since %ds after retry %d (max %d).", time.time() - self._last_snd, self._retry_snd, self._retry_max)
			return True
		if time.time() - self._last_rcv > self._hb_delay * 2:
			self._log_rcv.waring("Nothing has been received since %ds.", time.time() - self._last_rcv)
			return True
		return False

	def get_status(self):
		return self._statusToName.get(self._status, self._statusToName[self.KO])

	def send_test(self):
		try:
			response = requests.get(self._url, verify=False)
			if response.status_code != requests.codes.ok:
				self._log_snd.error('Test error: %s %s', response.status.code, response.status.message)
				self._status &= ~self.CAN_SND
				self._retry_snd += 1
				return False
		except Exception as e:
			self._log_snd.exception('Test exception: %s', e.message)
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
				r = requests.post(self._url, json=msgs, timeout=(0.5, 120), verify=False)
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
		while not self._stopworker:
			if self.qToJ.empty():
				if time.time() - self._last_snd > self._hb_delay:
					self.qToJ.put({"cmd":"hb"}) # Add a heartbeat if it has been too long
					self._last_snd = time.time() - (self._hb_delay / 2) # Next retry in half _hb_delay to avoid sending continuously
				else:
					time.sleep(0.1)
					continue # Check if stopworker changed
			msgs = []
			while not self._stopworker and not self.qToJ.empty() and (len(msgs) < max_msg_per_send):
				msg = self.qToJ.get()
				msgs.append(msg)
			self._log_snd.debug("Sending %d msgs", len(msgs))
			self.send(msgs)
			# TODO Put back messages in qToJ if send failed !
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
				self.server._last_rcv = time.time()
				self._log.verbose("Client [%s:%d] disconnected", *(self.client_address))
		self._socketIn = socketserver.TCPServer((self._socket_host, self._socket_port), SockIn)
		if self._socketIn:
			self._socketIn.jmsg = self
			threading.Thread(target=self._loopRcv, args=(), name="SockIn").start()
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
		# self._url = self._callback+'?apikey='+self._apikey     # TODO Check how to send brkDown+daemonDown without uid in url
		self._status &= ~self.CAN_RCV
		self._socketIn = None
		self._log_rcv.debug("Stopped")

