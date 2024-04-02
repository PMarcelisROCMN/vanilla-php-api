<?php

require_once('DB.php');
require_once('../model/Response.php');

try{
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
}catch(PDOException $ex){
    error_log("Connection Error - ".$ex, 0);
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(500);
    $response->addMessage("Database Connection Error");
    $response->send();
    exit;
}