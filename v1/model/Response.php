<?php

class Response {

    private $_success;
    private $_httpStatusCode;
    private $_message = array();
    private $_data;
    private $_toCache = false;
    private $_responseData = array();

    public function setSuccess($success) {
        $this->_success = $success;
    }

    public function setHttpStatusCode($httpStatusCode) {
        $this->_httpStatusCode = $httpStatusCode;
    }

    public function addMessage($message) {
        $this->_message[] = $message;
    }

    public function setData($data) {
        $this->_data = $data;
    }

    public function toCache($toCache) {
        $this->_toCache = $toCache;
    }

    public function send() {
        // response header
        header('Content-type: application/json;charset=utf-8');

        if ($this->_toCache == true) {
            // cache the response for 60 seconds
            // if it is not changed, the client can use the cached response
            header('Cache-control: max-age=60');
        } else {
            header('Cache-control: no-cache, no-store');
        }

        //check if the response is valid
        if (!is_bool($this->_success) || !is_numeric($this->_httpStatusCode)) {

            // set the response code
            http_response_code(500);

            // set the response data
            $this->_responseData['statusCode'] = 500;
            $this->_responseData['success'] = false;
            $this->addMessage('Response creation error');
            $this->_responseData['message'] = $this->_message;
        } else {
            http_response_code($this->_httpStatusCode);
            $this->_responseData['statusCode'] = $this->_httpStatusCode;
            $this->_responseData['success'] = $this->_success;
            $this->_responseData['message'] = $this->_message;
            $this->_responseData['data'] = $this->_data;
        }

        echo json_encode($this->_responseData);
    }
}