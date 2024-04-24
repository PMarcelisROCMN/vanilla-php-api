<?php

require_once('db.php');
require_once('../model/Task.php');
require_once('../model/Response.php');

require_once('../taskscrud/Create.php');
require_once('../taskscrud/Update.php');
require_once('../taskscrud/Read.php');
require_once('../taskscrud/Delete.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(500);
    $response->addMessage("Database connection error");
    $response->send();
    exit();
}


// Integration of authentication script
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
    $response = new Response();
    $response->setSuccess(false);
    // no access
    $response->setHttpStatusCode(401);
    !isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false;
    strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false;
    $response->send();
    exit();
}

$accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

try {
    $query = $writeDB->prepare('SELECT userid, accesstokenexpiry, useractive, loginattempts FROM tblsessions, tblusers WHERE tblsessions.userid = tblusers.id AND accesstoken = :accesstoken');
    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(401);
        $response->addMessage("Invalid access token");
        $response->send();
        exit();
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $returned_userid = $row['userid'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry'];
    $returned_useractive = $row['useractive'];
    $returned_loginattempts = $row['loginattempts'];

    if ($returned_useractive !== 'Y') {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(401);
        $response->addMessage("User account not active");
        $response->send();
        exit();
    }

    // check if the access token has expired
    $accesstokenexpiry = strtotime($returned_accesstokenexpiry);
    if ($accesstokenexpiry < time()) {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(401);
        $response->addMessage("Access token has expired");
        $response->send();
        exit();
    }

    // check if the user has too many login attempts
    if ($returned_loginattempts >= 3) {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(401);
        $response->addMessage("User account is currently locked out");
        $response->send();
        exit();
    }
} catch (PDOException $ex) {
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(500);
    $response->addMessage("There was an issue authenticating - please try again");
    $response->send();
    exit();
}
// end of authentication script

// create all the objects that we need for creating, updating, reading and deleting tasks
$taskRead = new Read($readDB);
$taskCreate = new Create($writeDB, $readDB);
$taskDelete = new Delete($writeDB);
$taskUpdate = new Update($writeDB);

// check if the key 'taskid' is in the query string
if (array_key_exists("taskid", $_GET)) {
    
    $taskid = $_GET['taskid'];

    if ($taskid == '' || !is_numeric($taskid)) {
        $response = new Response();
        // declined request because of client data
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task ID cannot be blank or must be numeric");
        $response->send();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $taskRead->getSingularTask($taskid, $returned_userid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
       $taskDelete->deleteTask($taskid, $returned_userid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $taskUpdate->updatetask($taskid, $returned_userid);
    } else {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
    }
}
// to get all tasks that are completed or incompleted
// tasks/completed=Y or tasks/completed=N
else if (array_key_exists("completed", $_GET)) {

    $completed = $_GET["completed"];

    if ($completed !== 'Y' && $completed !== 'N') {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $response->addMessage("Completed filter must be Y or N.");
        $response->send();
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $taskRead->getCompleteTasks($completed, $returned_userid);
    } else {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(405);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit();
    }
} else if (array_key_exists("page", $_GET)) {

    $page = $_GET['page'];

    if ($page == '' || !is_numeric($page)) {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $response->addMessage("Page number cannot be blank and must be numeric");
        $response->send();
        exit();
    }

    // limit of the number of tasks per page
    // todo: find some way to be able to change this value
    $limitPerPage = 10;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $taskRead->getTasksPage($returned_userid, $page, $limitPerPage);
    } else {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(405);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit();
    }
}
// get all tasks api/vi/tasks
else if (empty($_GET)) {

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $taskRead->getAllTasks($returned_userid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $taskCreate->createTask($returned_userid);
    } else {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(405);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit();
    }
} else {
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(404);
    $response->addMessage("Endpoint not found");
    $response->send();
    exit();
}