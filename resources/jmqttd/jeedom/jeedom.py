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
#

import time
import logging
import threading
import requests
import datetime
import collections
import os
from os.path import join
import socket
from queue import Queue
import socketserver
from socketserver import (TCPServer, StreamRequestHandler)
import signal
import unicodedata
import sys
if sys.version_info.major == 3:
    unicode = str

# ------------------------------------------------------------------------------

class jeedom_com():
    def __init__(self,apikey = '',url = '',cycle = 0.5,retry = 3):
        self.apikey = apikey
        self.url = url
        self.cycle = cycle
        self.retry = retry
        self.changes = {}
        if cycle > 0 :
            self.send_changes_async()
        logging.debug('Init request module v%s', requests.__version__)

    def send_changes_async(self):
        try:
            if len(self.changes) == 0:
                resend_changes = threading.Timer(self.cycle, self.send_changes_async)
                resend_changes.start()
                return
            start_time = datetime.datetime.now()
            changes = self.changes
            self.changes = {}
            logging.debug('Send to jeedom : %s', changes)
            i = 1
            while i <= self.retry:
                try:
                    r = requests.post(self.url + '?apikey=' + self.apikey, json=changes, timeout=(0.5, 120), verify=False)
                    if r.status_code == requests.codes.ok:
                        break
                except Exception as error:
                    logging.error('Error when sending request to jeedom: %s (on try %d/%d)', error, i, self.retry)
                i = i + 1
            if r.status_code != requests.codes.ok:
                logging.error('Failed to send request to jeedom after %d retries, return code %s', self.retry, r.status_code)
            dt = datetime.datetime.now() - start_time
            ms = (dt.days * 24 * 60 * 60 + dt.seconds) * 1000 + dt.microseconds / 1000.0
            timer_duration = self.cycle - ms
            if timer_duration < 0.1 :
                timer_duration = 0.1
            if timer_duration > self.cycle:
                timer_duration = self.cycle
            resend_changes = threading.Timer(timer_duration, self.send_changes_async)
            resend_changes.start()
        except Exception as error:
            logging.exception('Exception on send_changes_async')
            resend_changes = threading.Timer(self.cycle, self.send_changes_async)
            resend_changes.start()

    def add_changes(self,key,value):
        if key.find('::') != -1:
            tmp_changes = {}
            changes = value
            for k in reversed(key.split('::')):
                if k not in tmp_changes:
                    tmp_changes[k] = {}
                tmp_changes[k] = changes
                changes = tmp_changes
                tmp_changes = {}
            if self.cycle <= 0:
                self.send_change_immediate(changes)
            else:
                self.merge_dict(self.changes,changes)
        else:
            if self.cycle <= 0:
                self.send_change_immediate({key:value})
            else:
                self.changes[key] = value

    def send_change_immediate(self,change):
        threading.Thread( target=self.thread_change,args=(change,)).start()

    def thread_change(self,change):
        logging.debug('Send to jeedom : %s', change)
        i = 1
        while i <= self.retry:
            try:
                r = requests.post(self.url + '?apikey=' + self.apikey, json=change, timeout=(0.5, 120), verify=False)
                if r.status_code == requests.codes.ok:
                    break
            except Exception as error:
                logging.error('Error when sending request to jeedom: %s (on try %d/%d)', error, i, self.retry)
            i = i + 1

    def set_change(self,changes):
        self.changes = changes

    def get_change(self):
        return self.changes

    def merge_dict(self,d1, d2):
        for k,v2 in d2.items():
            v1 = d1.get(k) # returns None if v1 has no value for this key
            if ( isinstance(v1, collections.Mapping) and
                isinstance(v2, collections.Mapping) ):
                self.merge_dict(v1, v2)
            else:
                d1[k] = v2

    def test(self):
        try:
            response = requests.get(self.url + '?apikey=' + self.apikey, verify=False)
            if response.status_code != requests.codes.ok:
                logging.error('Callback error: %s %s. Please check your network configuration page', response.status.code, response.status.message)
                return False
        except Exception as e:
            logging.exception('Callback result as a unknown exception: %s. Please check your network configuration page', e.message)
            return False
        return True

# ------------------------------------------------------------------------------

class jeedom_utils():

    @staticmethod
    def convert_log_level(level = 'error'):
        LEVELS = {  'debug': logging.DEBUG,
                    'info': logging.INFO,
                    'notice': logging.WARNING,
                    'warning': logging.WARNING,
                    'error': logging.ERROR,
                    'critical': logging.CRITICAL,
                    'none': logging.CRITICAL}
        return LEVELS.get(level, logging.CRITICAL)

    @staticmethod
    def set_log_level(level = 'error'):
        FORMAT = '[%(asctime)-15s][%(levelname)-8s] : %(message)s'
        logging.basicConfig(level=jeedom_utils.convert_log_level(level),format=FORMAT, datefmt="%Y-%m-%d %H:%M:%S")

    # @staticmethod
    # def find_tty_usb(idVendor, idProduct, product = None):
    #   context = pyudev.Context()
    #   for device in context.list_devices(subsystem='tty'):
    #       if 'ID_VENDOR' not in device:
    #           continue
    #       if device['ID_VENDOR_ID'] != idVendor:
    #           continue
    #       if device['ID_MODEL_ID'] != idProduct:
    #           continue
    #       if product is not None:
    #           if 'ID_VENDOR' not in device or device['ID_VENDOR'].lower().find(product.lower()) == -1 :
    #               continue
    #       return str(device.device_node)
    #   return None

    @staticmethod
    def stripped(str):
        return "".join([i for i in str if i in range(32, 127)])

    @staticmethod
    def ByteToHex( byteStr ):
        return byteStr.hex()

    @staticmethod
    def dec2bin(x, width=8):
        return ''.join(str((x>>i)&1) for i in range(width-1,-1,-1))

    @staticmethod
    def dec2hex(dec):
        if dec is None:
            return '0x00'
        return "0x{:02X}".format(dec)

    @staticmethod
    def testBit(int_type, offset):
        mask = 1 << offset
        return(int_type & mask)

    @staticmethod
    def clearBit(int_type, offset):
        mask = ~(1 << offset)
        return(int_type & mask)

    @staticmethod
    def split_len(seq, length):
        return [seq[i:i+length] for i in range(0, len(seq), length)]

    @staticmethod
    def write_pid(path):
        pid = str(os.getpid())
        logging.debug("Writing PID %s to %s", pid, path)
        open(path, 'w').write("%s\n" % pid)

    @staticmethod
    def remove_accents(input_str):
        nkfd_form = unicodedata.normalize('NFKD', unicode(input_str))
        return u"".join([c for c in nkfd_form if not unicodedata.combining(c)])

    @staticmethod
    def printHex(hex):
        return ' '.join([hex[i:i + 2] for i in range(0, len(hex), 2)])

# ------------------------------------------------------------------------------

class jeedom_socket_handler(StreamRequestHandler):
    def handle(self):
        logging.debug("Client [%s:%d] connected", *(self.client_address))
        lg = self.rfile.readline()
        self.server.queue.put(lg)
        logging.debug("Message read from socket: %s", lg.strip())
        self.netAdapterClientConnected = False
        logging.debug("Client [%s:%d] disconnected", *(self.client_address))

class jeedom_socket():

    def __init__(self,address='localhost', port=55000):
        self.address = address
        self.port = port
        self.queue = Queue()
        self.get = self.queue.get
        self.empty = self.queue.empty
        self.netAdapter = None
        socketserver.TCPServer.allow_reuse_address = True

    def open(self):
        if self.netAdapter is not None:
            logging.warning("Socket interface already started")
            return
        self.netAdapter = TCPServer((self.address, self.port), jeedom_socket_handler)
        if self.netAdapter:
            self.netAdapter.queue = self.queue
            logging.debug("Socket interface started")
            threading.Thread(target=self.loopNetServer, args=()).start()
        else:
            logging.debug("Cannot start socket interface")

    def loopNetServer(self):
        logging.debug("LoopNetServer Thread started")
        logging.debug("Listening on: [%s:%d]", self.address, self.port)
        self.netAdapter.serve_forever()
        logging.debug("LoopNetServer Thread stopped")

    def close(self):
        self.netAdapter.shutdown()

    def getMessage(self):
        return self.message

# ------------------------------------------------------------------------------
# END
# ------------------------------------------------------------------------------
