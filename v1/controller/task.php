<?php

require_once('db.php');
require_once('../model/Task.php');
require_once('../model/Response.php');

require_once('../taskHandlers/Create.php');
require_once('../taskHandlers/Update.php');
require_once('../taskHandlers/Read.php');
require_once('../taskHandlers/Delete.php');


// Start with the DB connection
try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    error_log("Connection error - " . $ex, 0);
    new Response(false, 500, "Database connection error . " . $ex->getMessage());
    exit();
}


// Integration of authentication script
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
    $messages = [];
    !isset($_SERVER['HTTP_AUTHORIZATION']) ? $messages[] = "Access token is missing from the header" : null;
    strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $messages[] = "Access token cannot be blank" : null;
    new Response(false, 401, $messages);
    exit();
}

$accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

try {
    $query = $writeDB->prepare('SELECT userid, accesstokenexpiry, useractive, loginattempts FROM tblsessions, tblusers WHERE tblsessions.userid = tblusers.id AND accesstoken = :accesstoken');
    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        new Response(false, 401, "Invalid access token");
        exit();
    }

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $returned_userid = $row['userid'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry'];
    $returned_useractive = $row['useractive'];
    $returned_loginattempts = $row['loginattempts'];

    if ($returned_useractive !== 'Y') {
        new Response(false, 401, "User account not active");
        exit();
    }

    // check if the access token has expired
    $accesstokenexpiry = strtotime($returned_accesstokenexpiry);
    if ($accesstokenexpiry < time()) {
        new Response(false, 401, "Access token has expired");
        exit();
    }

    // check if the user has too many login attempts
    if ($returned_loginattempts >= 3) {
        new Response(false, 401, "User account is currently locked out");
        exit();
    }
} catch (PDOException $ex) {
    new Response(false, 500, "There was an issue authenticating - please try again");
    exit();
}
// end of authentication script

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS'){
    // only allowed methods are POST and OPTIONS
    header('Access-Control-Allow-Methods: POST, GET, PATCH, DELETE, OPTIONS');
    // set the headers that are allowed
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    // set the max age of the preflight request.
    // the same response can be called without sending a preflight request for the duration.
    header('Access-Control-Max-Age: 86400');
    new Response(true, 200);
    exit;
}

// create all the objects that we need for creating, updating, reading and deleting tasks
$taskRead = new Read($readDB);
$taskCreate = new Create($writeDB, $readDB);
$taskDelete = new Delete($writeDB);
$taskUpdate = new Update($writeDB);

// check if the key 'taskid' is in the query string
if (array_key_exists("taskid", $_GET)) {

    $taskid = $_GET['taskid'];

    if ($taskid == '' || !is_numeric($taskid)) {
        new Response(false, 400, "Task ID cannot be blank or must be numeric");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $taskRead->getSingularTask($taskid, $returned_userid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
       $taskDelete->deleteTask($taskid, $returned_userid);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $taskUpdate->updatetask($taskid, $returned_userid);
    } else {
        new Response(false, 405, "Request method not allowed");
        exit();
    }
}
// to get all tasks that are completed or incompleted
// tasks/completed=Y or tasks/completed=N
else if (array_key_exists("completed", $_GET)) {

    $completed = $_GET["completed"];

    if ($completed !== 'Y' && $completed !== 'N') {
        new Response(false, 400, "Completed filter must be Y or N.");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $taskRead->getCompleteTasks($completed, $returned_userid);
    } else {
        new Response(false, 405, "Request method not allowed");
        exit();
    }
} else if (array_key_exists("page", $_GET)) {

    $page = $_GET['page'];

    if ($page == '' || !is_numeric($page)) {
        new Response(false, 400, "Page number cannot be blank and must be numeric");
        exit();
    }

    // limit of the number of tasks per page
    // todo: find some way to be able to change this value
    $limitPerPage = 10;

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $taskRead->getTasksPage($returned_userid, $page, $limitPerPage);
    } else {
        new Response(false, 405, "Request method not allowed");
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
        new Response(false, 405, "Request method not allowed");
        exit();
    }
} else {
    new Response(false, 404, "Endpoint not found");
    exit();
}
