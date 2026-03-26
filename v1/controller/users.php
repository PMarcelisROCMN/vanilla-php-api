<?php

require_once 'db.php';
require_once '../model/Response.php';

// Connect to the write database
try {
    $writeDB = DB::connectWriteDB();
}
catch (Exception $ex) {
    error_log("Connection error: " . $ex->getMessage(), 0);
    new Response(false, 500, "Database connection error");
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
    new Response(true, 200);
    exit;
}

// Check if CONTENT_TYPE is set before accessing it
$contentType = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';

if (empty($contentType)) {
    // Handle the case where the content type is missing
    new Response(false, 400, "Content-Type header is missing");
    exit();
}

// check if the user isn't using the correct post method
// we will always be using post for user related requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
    new Response(false, 405, "Request method not allowed");
    exit;
}

// check if the content type is set to json
if ($_SERVER['CONTENT_TYPE'] !== 'application/json'){
    new Response(false, 400, "Content type header not set to JSON");
    exit;
}

// get the raw post data
$rawPOSTData = file_get_contents('php://input');

// check if the json data is valid
if (!$jsonData = json_decode($rawPOSTData)){
    new Response(false, 400, "Request body is not valid JSON");
    exit;
}

// check if the required fields are set
if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)){
    $messages = [];
    !isset($jsonData->fullname) ? $messages[] = "Full name field is mandatory" : null;
    !isset($jsonData->username) ? $messages[] = "Username field is mandatory" : null;
    !isset($jsonData->password) ? $messages[] = "Password field is mandatory" : null;
    new Response(false, 400, $messages);
    exit;
}

// check if the fields are empty
// check if the fields are too long
// Could add more fields to make sure that users have specific requirements for creating an account (e.g password must contain a number, a special character, etc)
if (strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 || strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
    $messages = [];
    strlen($jsonData->fullname) < 1 ? $messages[] = "Full name cannot be blank" : null;
    strlen($jsonData->fullname) > 255 ? $messages[] = "Full name cannot be greater than 255 characters" : null;
    strlen($jsonData->username) < 1 ? $messages[] = "Username cannot be blank" : null;
    strlen($jsonData->username) > 255 ? $messages[] = "Username cannot be greater than 255 characters" : null;
    strlen($jsonData->password) < 1 ? $messages[] = "Password cannot be blank" : null;
    strlen($jsonData->password) > 255 ? $messages[] = "Password cannot be greater than 255 characters" : null;
    new Response(false, 400, $messages);
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
        // 409 is a conflict status code
        new Response(false, 409, "Username already exists");
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
        new Response(false, 500, "There was an issue creating a user account - please try again");
        exit;
    }

    $lastUserID = $writeDB->lastInsertId();

    $returnData = array();
    $returnData['user_id'] = $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    new Response(true, 201, "User created", $returnData);
    exit;

}
catch (PDOException $ex){
    new Response(false, 500, "There was an issue creating a user account - please try again");
    exit;
}
