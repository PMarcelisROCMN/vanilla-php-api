<?php

require_once 'db.php';
require_once '../model/Response.php';

try
{
    $writeDB = DB::connectWriteDB();
}
catch (PDOException $ex)
{
    error_log("Connection error: " . $ex->getMessage(), 0);

    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage("Database connection error");
    $response->send();
    exit;
}

// Delete or update a specific session
if (array_key_exists("sessionid", $_GET)){

    $sessionid = $_GET['sessionid'];

    // validation for the sessionid
    if ($sessionid === '' || !is_numeric($sessionid)){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $sessionid === '' ? $response->addMessage("Session ID cannot be blank") : false;
        !is_numeric($sessionid) ? $response->addMessage("Session ID must be numeric") : false;
        $response->send();
        exit;
    }

    // check if the HTTP Authorization header is set or if the length is less than 1
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(401);
        !isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false;
        strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false;
        $response->send();
        exit;
    }

    // get the access token from the header
    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    // attempt to delete a row from the tblsessions table
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE'){
    try {

        $query = $writeDB->prepare('DELETE FROM tblsessions WHERE id = :sessionid AND accesstoken = :accesstoken');
        $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->execute();

        // get the number of rows affected
        $rowCount = $query->rowCount();

        // if the number of rows affected is 0, then the session was not deleted
        if ($rowCount === 0){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage("Failed to log out of this session using this access token");
            $response->send();
            exit;
        }

        // if the session was deleted, return a success message
        $returnData = array();
        $returnData['session_id'] = intval($sessionid);

        $response = new Response();
        $response->setSuccess(true);
        $response->setHttpStatusCode(200);
        $response->addMessage("Logged out");
        $response->setData($returnData);
        $response->send();
        exit;

        // if there is an issue with the database, return an error message
        }catch(PDOException $ex){
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue logging out - please try again");
            $response->send();
            exit;
        }
    } 
    // refreshing an access token
    else if ($_SERVER['REQUEST_METHOD'] === 'PATCH'){

        // check if the content type header is set to JSON
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json'){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage("Content type header not set to JSON");
            $response->send();
            exit;
        }

        // get the raw data from the request
        $rawPatchData = file_get_contents('php://input');

        // check if the data is valid JSON
        if (!$jsonData = json_decode($rawPatchData, true)){ // if the data is not valid JSON
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage("Request body is not valid JSON");
            $response->send();
            exit;
        }

        // check if the refresh token is set
        if (!isset($jsonData['refresh_token']) || strlen($jsonData['refresh_token']) < 1){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            !isset($jsonData['refresh_token']) ? $response->addMessage("Refresh token not supplied") : false;
            strlen($jsonData['refresh_token']) < 1 ? $response->addMessage("Refresh token cannot be blank") : false;
            $response->send();
            exit;
        }

        try{

            $refreshtoken = $jsonData['refresh_token'];
            /* Select the session from the database
            sessionid, userid, accesstoken, refreshtoken, useractive, loginattempts, accesstokenexpiry, refreshtokenexpiry
            from the users and sessions tables
            We need to provide the sessionid, accesstoken and refreshtoken

            we're joining two tables together to link the session id with the user.
            Then we're trying to search for a session where the session id, access token and refresh token match the ones provided
            */
            $query = $writeDB->prepare('SELECT 
            tblsessions.id as sessionid, 
            tblsessions.userid as userid, 
            accesstoken, 
            refreshtoken, 
            useractive, 
            loginattempts, 
            accesstokenexpiry, 
            refreshtokenexpiry FROM tblsessions, 
            tblusers WHERE tblsessions.userid = tblusers.id 
            AND tblsessions.id = :sessionid 
            AND tblsessions.accesstoken = :accesstoken 
            AND tblsessions.refreshtoken = :refreshtoken');

            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if($rowCount == 0){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(401);
                $response->addMessage("Access token or refresh token is incorrect for session id");
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            // get the values from the database
            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_useractive = $row['useractive'];
            $returned_loginattempts = $row['loginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            // if a user is not active, they can't be logged in again, so we can't refresh the access token
            if ($returned_useractive !== 'Y'){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(401);
                $response->addMessage("User account is not active");
                $response->send();
                exit;
            }

            // if a user has had 3 failed login attempts, lock the account
            if ($returned_loginattempts >= 3){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(401);
                $response->addMessage("User account is currently locked out");
                $response->send();
                exit;
            }

            // check if the refresh token has expired
            if (strtotime($returned_refreshtokenexpiry) < time()){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(401);
                $response->addMessage("Refresh token has expired - please log in again");
                $response->send();
                exit;
            }

            // generate new access token and refresh token
            $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
            $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

            // calculate the expiry date for the access token
            $access_token_expiry_seconds = 1200;
            $refresh_token_expiry_seconds = 1209600;

            // update the session in the database
            $query = $writeDB->prepare(
            'UPDATE tblsessions SET 
            accesstoken = :accesstoken, 
            accesstokenexpiry = date_add(NOW(), INTERVAL :accesstokenexpiry SECOND ), 
            refreshtoken = :refreshtoken, 
            refreshtokenexpiry = date_add(NOW(), INTERVAL :refreshtokenexpiry SECOND ) 
            WHERE id = :sessionid AND userid = :userid AND accesstoken = :currentaccesstoken AND refreshtoken = :currentrefreshtoken'
            );

            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiry', $access_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':refreshtoken', $refreshToken, PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiry', $refresh_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':currentaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
            $query->bindParam(':currentrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(401);
                $response->addMessage("Access token could not be refreshed - please log in again");
                $response->send();
                exit;
            }

            $returnData = array();
            $returnData['session_id'] = $returned_sessionid;
            $returnData['access_token'] = $accessToken;
            $returnData['access_token_expiry'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refreshToken;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

            $response = new Response();
            $response->setSuccess(true);
            $response->setHttpStatusCode(200);
            $response->addMessage("Token refreshed");
            $response->setData($returnData);
            $response->send();
            exit;

        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage("There was an issue refreshing the access token - please log in again");
            $response->send();
            exit;
        }
    } 
    else {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(405);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }
    
// log in - create a new session
}else if (empty($_GET)){

    if ($_SERVER['REQUEST_METHOD'] !== 'POST'){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(405);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }

    // to stop a ton of requests being made to the server
    // adding a delay per request makes no difference for a real user, 
    // but will stop a bot more effectively
    sleep(1);

    if ($_SERVER['CONTENT_TYPE'] !== 'application/json'){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $response->addMessage("Content type header not set to JSON");
        $response->send();
        exit;
    }

    $rawPostData = file_get_contents('php://input');

    if (!$jsonData = json_decode($rawPostData)){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $response->addMessage("Request body is not valid JSON");
        $response->send();
        exit;
    }

    if (!isset($jsonData->username) || !isset($jsonData->password)){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        (!isset($jsonData->username) ? $response->addMessage("Username not supplied") : false);
        (!isset($jsonData->password) ? $response->addMessage("Password not supplied") : false);
        $response->send();
        exit;
    }

    if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        (strlen($jsonData->username) < 1 ? $response->addMessage("Username cannot be blank") : false);
        (strlen($jsonData->username) > 255 ? $response->addMessage("Username cannot be greater than 255 characters") : false);
        (strlen($jsonData->password) < 1 ? $response->addMessage("Password cannot be blank") : false);
        (strlen($jsonData->password) > 255 ? $response->addMessage("Password cannot be greater than 255 characters") : false);
        $response->send();
        exit;
    }

    try {

        $username = $jsonData->username;
        $password = $jsonData->password;

        $query = $writeDB->prepare('SELECT id, fullname, username, password, useractive, loginattempts FROM tblusers WHERE username = :username');
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        // if the username is not found
        if ($rowCount === 0){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(401);
            $response->addMessage("Username or password is incorrect");
            $response->send();
            exit;
        }

        // get the row from the database
        $row = $query->fetch(PDO::FETCH_ASSOC);

        // get the values from the database
        $returnedId = $row['id'];
        $returnedFullname = $row['fullname'];
        $returnedUsername = $row['username'];
        $returnedPassword = $row['password'];
        $returnedUserActive = $row['useractive'];
        $returnedLoginAttempts = $row['loginattempts'];

        // if a user is not active, they can't be logged in
        if ($returnedUserActive !== 'Y'){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(401);
            $response->addMessage("User account is not active");
            $response->send();
            exit;
        }

        // if a user has had 3 failed login attempts, lock the account
        if ($returnedLoginAttempts >= 3){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(401);
            $response->addMessage("User account is currently locked out");
            $response->send();
            exit;
        }

        // check if the password is correct and if not, increment the login attempts
        if (!password_verify($password, $returnedPassword)){
            $query = $writeDB->prepare('UPDATE tblusers SET loginattempts = loginattempts+1 WHERE id = :id');
            $query->bindParam(':id', $returnedId, PDO::PARAM_INT);
            $query->execute();

            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(401);
            $response->addMessage("Username or password is incorrect");
            $response->send();
            exit;
        }

        // generate access token and refresh token
        $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
        $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

        // calculate the expiry date for the access token
        // 1200 seconds is 20 minutes
        $access_token_expiry_seconds = 1200;
        // 1209600 seconds is 2 weeks
        $refresh_token_expiry_seconds = 1209600;

        $returnData = array();

        $returnData['session_id'] = $accessToken;
        $returnData['access_token'] = $accessToken;
        
    } catch (PDOException $ex) {
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage("There was an issue logging in - please try again");
        $response->send();
        exit;
    }

    try {
        $writeDB->beginTransaction();

        $query = $writeDB->prepare('UPDATE tblusers SET loginattempts = 0 WHERE id = :id');
        $query->bindParam(':id', $returnedId, PDO::PARAM_INT);
        $query->execute();

        $query = $writeDB->prepare('INSERT INTO tblsessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) VALUES (:userid, :accesstoken, date_add(NOW(), INTERVAL :accesstokenexpiry SECOND), :refreshtoken, date_add(NOW(), INTERVAL :refreshtokenexpiry SECOND))');
        $query->bindParam(':userid', $returnedId, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiry', $access_token_expiry_seconds, PDO::PARAM_INT);
        $query->bindParam(':refreshtoken', $refreshToken, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiry', $refresh_token_expiry_seconds, PDO::PARAM_INT);
        $query->execute();

        $lastSessionID = $writeDB->lastInsertId();

        // commit the transaction to the database
        $writeDB->commit();

        $returnData = array();
        $returnData['session_id'] = $lastSessionID;
        $returnData['access_token'] = $accessToken;
        $returnData['access_token_expiry'] = $access_token_expiry_seconds;
        $returnData['refresh_token'] = $refreshToken;
        $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

        $response = new Response();
        $response->setSuccess(true);
        $response->setHttpStatusCode(201);
        $response->addMessage("Logged in");
        $response->setData($returnData);
        $response->send();
        exit;

    } catch (PDOException $ex) {
        // When beginning a transaction, will need to roll back if there is an error
        // This will prevent partial data being written to the database
        $writeDB->rollBack();
        $response = new Response();
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage($ex);
        $response->addMessage("TThere was an issue logging in - please try again");
        $response->send();
        exit;
    }
    
}else {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage("Endpoint not found");
    $response->send();
    exit;

}