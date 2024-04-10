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

        // if rowcount is 0, then the task was not found
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
    try {
        if($_SERVER['CONTENT_TYPE'] !== 'application/json'){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage("Content type header is not set to JSON");
            $response->send();
            exit();
        }

        $rawPatchData = file_get_contents('php://input');

        if(!$jsonData = json_decode($rawPatchData)){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage("Request body is not valid JSON");
            $response->send();
            exit();
        }

        $title_updated = false;
        $description_updated = false;
        $deadline_updated = false;
        $completed_updated = false;

        $queryFields = "";

        if(isset($jsonData->title)){
            $title_updated = true;
            $queryFields .= "title = :title, ";
        }

        if(isset($jsonData->description)){
            $description_updated = true;
            $queryFields .= "description = :description, ";
        }

        if(isset($jsonData->deadline)){
            $deadline_updated = true;
            $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
        }

        if(isset($jsonData->completed)){
            $completed_updated = true;
            $queryFields .= "completed = :completed, ";
        }

        $queryFields = rtrim($queryFields, ", ");

        if($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage("No task fields provided");
            $response->send();
            exit();
        }

        // Get the current task first
        $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid');
        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if($rowCount === 0){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(404);
            $response->addMessage("No task found to update");
            $response->send();
            exit();
        }

        while($row = $query->fetch(PDO::FETCH_ASSOC)){
            $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
        }

        $query = $writeDB->prepare('UPDATE tbltasks SET '.$queryFields.' WHERE id = :taskid');

        if($title_updated === true){
            $task->setTitle($jsonData->title);
            $up_title = $task->getTitle();
            $query->bindParam(':title', $jsonData->title, PDO::PARAM_STR);
        }

        if($description_updated === true){
            $task->setDescription($jsonData->description);
            $up_description = $task->getDescription();
            $query->bindParam(':description', $jsonData->description, PDO::PARAM_STR);
        }

        if($deadline_updated === true){
            $task->setDeadline($jsonData->deadline);
            $up_deadline = $task->getDeadline();
            $query->bindParam(':deadline', $jsonData->deadline, PDO::PARAM_STR);
        }

        if($completed_updated === true){
            $task->setCompleted($jsonData->completed);
            $up_completed = $task->getCompleted();
            $query->bindParam(':completed', $jsonData->completed, PDO::PARAM_STR);
        }

        $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if($rowCount === 0){
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage("Task not updated - check submitted data for errors");
            $response->send();
            exit();
        }

         $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid');
         $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
         $query->execute();

         $rowCount = $query->rowCount();

         if ($rowCount === 0){
             $response = new Response();
             $response->setSuccess(false);
             $response->setHttpStatusCode(500);
             $response->addMessage("Failed to retrieve task after update");
             $response->send();
             exit();
         }

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
         $response->setHttpStatusCode(201);
         $response->addMessage("Task retrieved after update");
         $response->setData($returnData);
         $response->send();
         exit();
    }
    catch(TaskException $ex){
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(400);
        $response->addMessage($ex->getMessage());
        $response->send();
        exit();
    }
    catch(PDOException $ex){
        error_log("Database query error - ".$ex, 0);
        $response = new Response();
        $response->setSuccess(false);
        $response->setHttpStatusCode(500);
        $response->addMessage("There was an issue updating task - please try again");
        $response->send();
        exit();
    }
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

        try{

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json'){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(400);
                $response->addMessage("Content type header is not set to JSON");
                $response->send();
                exit();
            }

            // get the raw post data
            // this allows you to inspect the body of the request that was sent
            // we're gonna try and decode that as json
            // we are using JSON because it is the most common format for APIs
            // almost all other programming languages have libraries to work with JSON
            // not everybody (every language) can send it through form data or url encoded data
            $rawPOSTData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPOSTData)){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(400);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit();
            }

            if (!isset($jsonData->title) || !isset($jsonData->completed)){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(400);
                (!isset($jsonData->title) ? $response->addMessage("Title field is mandatory and must be provided") : false);
                (!isset($jsonData->completed) ? $response->addMessage("Completed field is mandatory and must be provided") : false);
                $response->send();
                exit();
            }

            $newTask = new Task(
                null, 
                $jsonData->title, 
                (isset($jsonData->description) ? $jsonData->description : null), 
                (isset($jsonData->deadline) ? $jsonData->deadline : null), 
                $jsonData->completed
            );

            $title = $newTask->getTitle();
            $description = $newTask->getDescription();
            $deadline = $newTask->getDeadline();
            $completed = $newTask->getCompleted();

            $query = $writeDB->prepare('INSERT INTO tbltasks (title, description, deadline, completed) VALUES (:title, :description, STR_TO_DATE(:deadline, "%d/%m/%Y %H:%i"), :completed)');
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(500);
                $response->addMessage("Failed to create task");
                $response->send();
                exit();
            }

            // get last id of the item we just inserted
            $lastTaskID = $writeDB->lastInsertId();

            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid');
            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0){
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(500);
                $response->addMessage("Failed to retrieve task after creation");
                $response->send();
                exit();
            }

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
            $response->setHttpStatusCode(201);
            $response->addMessage("Task created");
            $response->setData($returnData);
            $response->send();
            exit();

        }catch(TaskException $ex){
            $response = new Response();
            $response->setSuccess(false);
            // 400 because the client data is wrong
            $response->setHttpStatusCode(400);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        }catch(PDOException $ex){
            error_log('Datasbase querry error - '. $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage('Failed to insert into database - check submitted data for errors');
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

} else {
    $response = new Response();
    $response->setSuccess(false);
    $response->setHttpStatusCode(404);
    $response->addMessage("Endpoint not found");
    $response->send();
    exit();
}