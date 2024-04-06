<?php

require_once('db.php');
require_once('../model/Task.php');
require_once('../model/Response.php');

// anything to do with a database can cause an exception.
// always wrap within a try-catch block
try
{
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
}
catch(PDOException $ex)
{
    // you never want to show the user the actual error message
    // it can contain sensitive data, so log it instead

    // log the error but don't send it to the user
    error_log("Connection Error - ".$ex, 0);

    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(500);
    $response->addMessage("Database Connection Error");
    $response->send();
    exit();
}

// check if the key 'taskid' is in the query string
if (array_key_exists("taskid", $_GET))
{
    $taskid = $_GET['taskid'];

    if ($taskid == '' || !is_numeric($taskid))
    {
        $response = new Response();
        // declined request because of client data
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task ID cannot be blank or must be numeric");
        $response->send();
    }

if($_SERVER['REQUEST_METHOD'] === 'GET')
{
    try {
        // :taskId is a placeholder for the actual value of $taskid that will be passed later to prevent SQL injection
        $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid');
        
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();
        if ($rowCount === 0)
        {
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(404);
            $response->addMessage("Task not found");
            $response->send();
            exit();
        }

        while ($row = $query->fetch(PDO::FETCH_ASSOC))
        {
            $task = new Task($row['id'], 
            $row['title'], 
            $row['description'], 
            $row['deadline'], 
            $row['completed']);
            $taskArray[] = $task->returnTaskAsArray();
        }

        $returnData = array();
        $returnData['rows_returned'] = $rowCount;
        $returnData['tasks'] = $taskArray;

        $response = new Response();
        $response->setSuccess(true);
        $response->setHttpStatusCode(200);
        $response->addMessage("Task retrieved");
        $response->setData($returnData);
        $response->toCache(true);
        $response->send();
        exit();
    }
    catch(TaskException $ex)
    {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $response->addMessage($ex->getMessage());
        $response->send();
        exit();
    }
    catch(PDOException $ex){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(500);
        $response->addMessage("There was an issue retrieving a task");
        $response->send();
        exit();
    }
}
elseif($_SERVER['REQUEST_METHOD'] === 'DELETE')
{
    try {
        $query = $writeDB->prepare('DELETE FROM tbltasks WHERE id = :taskid');
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        // if not succesful?????
        if($rowCount === 0){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(404);
            $response->addMessage("Task not found");
            $response->send();
            exit();
        }

        $response = new Response();
        $response->setSuccess(true);
        $response->setHttpStatusCode(200);
        $response->addMessage("Task deleted");
        $response->send();
        exit();
    }catch(PDOException $ex){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(500);
        $response->addMessage("There was an issue deleting a task");
        $response->send();
        exit();
    }
}
elseif($_SERVER['REQUEST_METHOD'] === 'PATCH')
{

}
else
{
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
}
}
// to get all tasks that are completed or incompleted
// tasks/completed=Y or tasks/completed=N
else if (array_key_exists("completed", $_GET)){

    $completed = $_GET["completed"];

    if ($completed !== 'Y' && $completed !== 'N'){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $response->addMessage("Completed filter must be Y or N.");
        $response->send();
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET'){

        try {

            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE completed = :completed');
            $query->bindValue(':completed', $completed, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setSuccess(true);
            $response->setHttpStatusCode(200);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();

        } catch(TaskException $ex){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        }
        catch(PDOException $ex){
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue retrieving tasks");
            $response->send();
            exit();
        }
 
    } else {
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(405);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit();
    }
}
else if (array_key_exists("page", $_GET)){

    $page = $_GET['page'];

    if ($page == '' || !is_numeric($page)){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $response->addMessage("Page number cannot be blank and must be numeric");
        $response->send();
        exit();
    }

    // limit of the number of tasks per page
    // todo: find some way to be able to change this value
    $limitPerPage = 2;

    if ($_SERVER['REQUEST_METHOD'] === 'GET'){

        try {
            $query = $readDB->prepare('SELECT COUNT(id) as totalNoOfTasks FROM tbltasks');
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            // get the total number of tasks
            $tasksCount = intval($row['totalNoOfTasks']);

            // calculate the number of pages needed to display all tasks
            $numOfPages = ceil($tasksCount / $limitPerPage);

            if ($numOfPages == 0){
                $numOfPages = 1;
            }

            if ($page > $numOfPages || $page == 0){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(404);
                $response->addMessage("Page not found");
                $response->send();
                exit();
            }

            // calculate the offset value for the SQL query to get the correct page
            // if page is 1 then the offset would be 0 since we're on the first page
            // otherwise, the offset would be the limitPerPage * (page - 1)
            // e.g. if page is 2, then offset would be 2 * (2 - 1) = 2 || if the limitPerPage is 20, then offset would be 20 * (2 - 1) = 20
            $offset = ($page == 1 ? 0 : ($limitPerPage * ($page - 1)));

            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks LIMIT :pglimit OFFSET :offset');
            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $tasksCount;
            $returnData['total_pages'] = $numOfPages;
            $returnData['has_next_page'] = $page < $numOfPages;
            $returnData['has_previous_page'] = $page > 1;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setSuccess(true);
            $response->setHttpStatusCode(200);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();
        }catch(TaskException $ex){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        }catch(PDOException $ex){
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue retrieving tasks");
            $response->send();
            exit();
        }
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
else if (empty($_GET)){

    if($_SERVER['REQUEST_METHOD'] === 'GET'){
        try{
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks');
            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setSuccess(true);
            $response->setHttpStatusCode(200);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();

        } catch(TaskException $ex){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch(PDOException $ex){
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue retrieving tasks");
            $response->send();
            exit();
        }
    }elseif($_SERVER['REQUEST_METHOD'] === 'POST'){

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