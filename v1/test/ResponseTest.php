<?php

require_once('../model/Response.php');

$response = new Response();

$response->setSuccess(true);
$response->setHttpStatusCode(200);
$response->addMessage('Test message 1');
$response->addMessage('Test message 2');
$response->addMessage('Test message 3');
$response->setData(
                array(
                    'testData' => 'testData',
                    'testData2' => 'testData2',
                    'testData3' => 'testData3'  
                ));
$response->toCache(true);
$response->send();