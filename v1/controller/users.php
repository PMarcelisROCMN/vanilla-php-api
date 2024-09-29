<?php

require_once 'db.php';
require_once '../model/Response.php';

// Connect to the write database
try {
    $writeDB = DB::connectWriteDB();
}
catch (Exception $ex) {
    error_log("Connection error: " . $ex->getMessage(), 0);

    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(500);
    $response->addMessage("Database connection error");
    $response->send();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS'){
    // only allowed methods are POST and OPTIONS
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    // set the headers that are allowed
    header('Access-Control-Allow-Headers: Content-Type');
    // set the max age of the preflight request. 
    // the same response can be called without sending a preflight request for the duration.
    header('Access-Control-Max-Age: 86400');
    $response = new Response();
    $response->setSuccess(true);
    $response->setHttpStatusCode(200);
    $response->send();
    exit;
}

// Check if CONTENT_TYPE is set before accessing it
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

if (empty($contentType)) {
    // Handle the case where the content type is missing
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(400);
    $response->addMessage("Content-Type header is missing");
    $response->send();
    exit();
}

// check if the user isn't using the correct post method
// we will always be using post for user related requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(405);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit;
}

// Check if CONTENT_TYPE is set before accessing it
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

if (empty($contentType)) {
    // Handle the case where the content type is missing
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(400);
    $response->addMessage("Content-Type header is missing");
    $response->send();
    exit();
}

// check if the content type is set to json
if ($_SERVER['CONTENT_TYPE'] !== 'application/json'){
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(400);
    $response->addMessage("Content type header not set to JSON");
    $response->send();
    exit;
}

// get the raw post data
$rawPOSTData = file_get_contents('php://input');

// check if the json data is valid
if (!$jsonData = json_decode($rawPOSTData)){
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(400);
    $response->addMessage("Request body is not valid JSON");
    $response->send();
    exit;
}

// check if the required fields are set
if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)){
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(400);
    (!isset($jsonData->fullname) ? $response->addMessage("Full name field is mandatory") : false);
    (!isset($jsonData->username) ? $response->addMessage("Username field is mandatory") : false);
    (!isset($jsonData->password) ? $response->addMessage("Password field is mandatory") : false);
    $response->send();
    exit;
}

// check if the fields are empty
// check if the fields are too long
// Could add more fields to make sure that users have specific requirements for creating an account (e.g password must contain a number, a special character, etc)
if (strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 || strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(400);
    (strlen($jsonData->fullname) < 1 ? $response->addMessage("Full name cannot be blank") : false);
    (strlen($jsonData->fullname) > 255 ? $response->addMessage("Full name cannot be greater than 255 characters") : false);
    (strlen($jsonData->username) < 1 ? $response->addMessage("Username cannot be blank") : false);
    (strlen($jsonData->username) > 255 ? $response->addMessage("Username cannot be greater than 255 characters") : false);
    (strlen($jsonData->password) < 1 ? $response->addMessage("Password cannot be blank") : false);
    (strlen($jsonData->password) > 255 ? $response->addMessage("Password cannot be greater than 255 characters") : false);
    $response->send();
    exit;
}

// store the values in variables and trim the values because we don't want any leading or trailing white spaces
$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = $jsonData->password;

try {

    $stmt = $writeDB->prepare('SELECT id FROM tblusers WHERE username = :username');
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->execute();

    $rowCount = $stmt->rowCount();

    // check if the username already exists
    if ($rowCount !== 0){
        $response = new Response();
        $response->setSuccess(false);
        // 409 is a conflict status code
        $response->setHttpStatusCode(409);
        $response->addMessage("Username already exists");
        $response->send();
        exit;
    }

    // hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $writeDB->prepare('INSERT INTO tblusers (fullname, username, password) VALUES (:fullname, :username, :password)');
    $stmt->bindParam(':fullname', $fullname, PDO::PARAM_STR);
    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
    $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    $stmt->execute();

    $rowCount = $stmt->rowCount();

    if ($rowCount === 0){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(500);
        $response->addMessage("There was an issue creating a user account - please try again");
        $response->send();
        exit;
    }

    $lastUserID = $writeDB->lastInsertId();

    $returnData = array();
    $returnData['user_id'] = $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    $response = new Response();
    $response->setSuccess(true);
    $response->setHttpStatusCode(201);
    $response->addMessage("User created");
    $response->setData($returnData);
    $response->send();
    exit;
    
}
catch (PDOException $ex){
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(500);
    $response->addMessage("There was an issue creating a user account - please try again");
    $response->send();
    exit;
}
