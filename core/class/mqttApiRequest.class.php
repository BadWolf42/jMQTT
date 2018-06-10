<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

class mqttApiRequest {

    const RET_TOPIC = 'topic';
    const APIKEY = 'apikey';
    const JRPC_JSONRPC = 'jsonrpc';
    const JRPC_VERSION = '2.0';
    const JRPC_METHOD = 'method';
    const JRPC_PARAMS = 'params';
    const JRPC_ID = 'id';
    const JRPC_ERR = 'error';
    const JRPC_RESULT = 'result';
    const JRPC_ERR_CODE = 'code';
    const JRPC_ERR_MSG = 'message';
    
    private $apiAddr;
    private $ret_topic = NULL;
    private $method;
    private $id = NULL;
    private $params = NULL;
    

    /**
     * Construct a new request from the given JSON defined request
     * @param string $_request
     */
    function __construct($_request) {

        log::add('jMQTT', 'info', 'API: processing request ' . $_request);
        
  	$this->apiAddr = 'http://' . config::byKey('internalAddr') . '/core/api/jeeApi.php';

        $errArr = NULL;
        $jsonArray = json_decode($_request, true);
        if (is_array($jsonArray) && json_last_error() == JSON_ERROR_NONE) {

            if (isset($jsonArray[self::RET_TOPIC]))
                $this->ret_topic = $jsonArray[self::RET_TOPIC];

            if (isset($jsonArray[self::JRPC_ID]))
                $this->id = $jsonArray[self::JRPC_ID];

            if (!isset($jsonArray[self::JRPC_METHOD]) || empty($jsonArray[self::JRPC_METHOD]))
                $errArr = self::newErrorArray(-32601, 'Method not found');
            else
                $this->method = $jsonArray[self::JRPC_METHOD];                
            
            $this->params = array(self::APIKEY => jeedom::getApiKey());
            if (isset($jsonArray[self::JRPC_PARAMS])) {
                if (is_array($jsonArray[self::JRPC_PARAMS]))
                    $this->params = array_merge($jsonArray[self::JRPC_PARAMS], $this->params);
                else
                    $errArr = self::newErrorArray(-32602, 'Invalid params: shall be an array', $this->id);
            }
        }
        else {
            $err = self::newErrorArray(-32600, 'Invalid request: cannot decode a JSON structure');
        }

        if (isset($errArr)) {
            $this->publishError($errArr);
            throw new Exception();
        }
    }

    /**
     * Process this request
     */
    public function processRequest() {

        if (!jMQTT::isApiEnable()) {
            $this->publishError(
                self::newErrorArray(-32001,
                                    "Vous n'êtes pas autorisé à effectuer cette action (API is disable)")
            );
            return;
        }            
        
        $request = json_encode(self::newJsonRpcArray(
            self::addParam(array(self::JRPC_METHOD => $this->method),
                           self::JRPC_PARAMS, $this->params),
            $this->id
        ));
        log::add('jMQTT', 'debug', 'API: jsonrpc request is ' . $request);

        $jsonRes = $this->send($request);
        log::add('jMQTT', 'debug', 'API: jsonrpc response is ' . $jsonRes);
        
        $arrRes = json_decode($jsonRes, true);
        if (!is_array($arrRes) || json_last_error() != JSON_ERROR_NONE ||
            !isset($arrRes[self::JRPC_JSONRPC]) || $arrRes[self::JRPC_JSONRPC] != self::JRPC_VERSION ||
            (!isset($arrRes[self::JRPC_RESULT]) && !isset($arrRes[self::JRPC_ERR])) ||
            (isset($arrRes[self::JRPC_ERR]) && !isset($arrRes[self::JRPC_ERR][self::JRPC_ERR_CODE]) && !isset($arrRes[self::JRPC_ERR][self::JRPC_ERR_MSG]))) {
            $arrRes = self::newErrorArray(-32603, 'Internal error', $this->id);
        }

        if (isset($arrRes[self::JRPC_ERR]))
            self::publishError($arrRes, $this->ret_topic);
        else
            $this->publishSuccess($jsonRes);
    }

    protected function send($_request, $_timeout = 15, $_maxRetry = 2) {
	$nbRetry = 0;
	while ($nbRetry < $_maxRetry) {
	    $ch = curl_init();
	    curl_setopt($ch, CURLOPT_URL, $this->apiAddr);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_TIMEOUT, $_timeout);
	    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	    curl_setopt($ch, CURLOPT_POST, true);
	    curl_setopt($ch, CURLOPT_POSTFIELDS, $_request);
	    curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
	    curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
	    $response = preg_replace('/[^[:print:]]/', '', trim(curl_exec($ch)));
	    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	    $nbRetry++;
	    if (curl_errno($ch) && $nbRetry < $_maxRetry) {
		curl_close($ch);
		usleep(500000);
	    } else {
		$nbRetry = $_maxRetry + 1;
	    }
	}
	if ($http_status == 301) {
	    if (preg_match('/<a href="(.*)">/i', $response, $r)) {
		$this->apiAddr = trim($r[1]);
		return $this->send($_request, $_timeout, $_file, $_maxRetry);
	    }
	}
	if ($http_status != 200) {
            $response = self::newErrorMsg(-32300, 'Erreur http : ' . $http_status . ' Details : ' . $response);
	}
	if (curl_errno($ch)) {
	    $response = self::newErrorMsg(-32400, 'Erreur curl sur : ' . $this->apiAddr . '. Détail :' . curl_error($ch));
	}
	curl_close($ch);
	return $response;
    }

    /**
     * Publish the given error array to the MQTT broker
     * @param array error message
     */
    protected function publishError($_arrErr) {
        log::add('jMQTT', 'error', 'API: ' . $_arrErr[self::JRPC_ERR][self::JRPC_ERR_MSG] . ' (err. code is ' .
                                   $_arrErr[self::JRPC_ERR][self::JRPC_ERR_CODE] . ')');
        if (isset($this->ret_topic))
            jMQTT::publishMosquitto('api', 'api', $this->ret_topic, json_encode($_arrErr), '1', '0');
    }

    /**
     * Publish the given result to the MQTT broker
     * @param string result (JSON encoded)
     */
    protected function publishSuccess($_jsonRes) {
        jMQTT::publishMosquitto('api', 'api', $this->ret_topic, $_jsonRes, '1', '0');
    }

    /**
     * Create and return an error message
     * @param _code int error code
     * @param _message string error message
     * @return error message (JSON encoded)
     */
    public static function newErrorMsg($_code, $_message) {
	return json_encode(self::newErrorArray($_code, $_message, $this->id));
    }

    /**
     * Create and return a success message
     * @param _result array result JSON array
     * @return success message (JSON encoded)
     */
    public static function newSuccessMsg($_result) {
        return json_encode(self::newJsonRpcArray(array(self::JRPC_RES => $_result), $this->id));
    }

    /**
     * Create and return an error array
     * @param _code int error code
     * @param _message string error message
     * @param _id string request id (optional - NULL by default)
     * @return error array (JSON)
     */
    private static function newErrorArray($_code, $_message, $_id = NULL) {
        return self::newJsonRpcArray(
            array(
                self::JRPC_ERR => array(
                    self::JRPC_ERR_CODE => $_code,
                    self::JRPC_ERR_MSG => $_message
                )),
            $_id);
    }

    /**
     * Create and return a new RPC JSON response array.
     * Return array i sinitialized from the given one and following is added : JSON RPC version (2.0),
     * and the request id. (if not NULL).
     * @param array $_array initilisation array (JSON)
     * @param _id string request id (optional - NULL by default)
     * @return array new JSON RPC response array
     */
    private static function newJsonRpcArray($_array, $_id = NULL) {
        return self::addParam(array_merge(array(self::JRPC_JSONRPC => self::JRPC_VERSION), $_array),
                              self::JRPC_ID, $_id);
    }

    /**
     * Add the given parameter the given array.
     * The parameter is defined by its key and is value; it is added if the value is set (isset returns true)
     * 
     */
    private static function addParam($_arr, $_key, $_value) {
        if (isset($_value))
            $_arr[$_key] = $_value;
        return $_arr;
    }
}
