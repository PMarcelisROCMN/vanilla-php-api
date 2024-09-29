<?php

class Update {

    private $writeDB;
    private $readDB;

    public function __construct($writeDB) {
        $this->writeDB = $writeDB;
    }
    
    public function updateTask($taskid, $returned_userid) {
    
        try {
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(400);
                $response->addMessage("Content type header is not set to JSON");
                $response->send();
                exit();
            }
    
            $rawPatchData = file_get_contents('php://input');
    
            if (!$jsonData = json_decode($rawPatchData)) {
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
    
            if (isset($jsonData->title)) {
                $title_updated = true;
                $queryFields .= "title = :title, ";
            }
    
            if (isset($jsonData->description)) {
                $description_updated = true;
                $queryFields .= "description = :description, ";
            }
    
            if (isset($jsonData->deadline)) {
                $deadline_updated = true;
                $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
            }
    
            if (isset($jsonData->completed)) {
                $completed_updated = true;
                $queryFields .= "completed = :completed, ";
            }
    
            $queryFields = rtrim($queryFields, ", ");
    
            if ($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated === false) {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(400);
                $response->addMessage("No task fields provided");
                $response->send();
                exit();
            }
    
            // Get the current task first
            $query = $this->writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
    
            $rowCount = $query->rowCount();
    
            if ($rowCount === 0) {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(404);
                $response->addMessage("No task found to update");
                $response->send();
                exit();
            }
    
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
            }
    
            $query = $this->writeDB->prepare('UPDATE tbltasks SET ' . $queryFields . ' WHERE id = :taskid AND userid = :userid');
    
            if ($title_updated === true) {
                $task->setTitle($jsonData->title);
                $up_title = $task->getTitle();
                $query->bindParam(':title', $jsonData->title, PDO::PARAM_STR);
            }
    
            if ($description_updated === true) {
                $task->setDescription($jsonData->description);
                $up_description = $task->getDescription();
                $query->bindParam(':description', $jsonData->description, PDO::PARAM_STR);
            }
    
            if ($deadline_updated === true) {
                $task->setDeadline($jsonData->deadline);
                $up_deadline = $task->getDeadline();
                $query->bindParam(':deadline', $jsonData->deadline, PDO::PARAM_STR);
            }
    
            if ($completed_updated === true) {
                $task->setCompleted($jsonData->completed);
                $up_completed = $task->getCompleted();
                $query->bindParam(':completed', $jsonData->completed, PDO::PARAM_STR);
            }
    
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
    
            $rowCount = $query->rowCount();
    
            if ($rowCount === 0) {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(400);
                $response->addMessage("Task not updated - check submitted data for errors");
                $response->send();
                exit();
            }
    
            $query = $this->writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
    
            $rowCount = $query->rowCount();
    
            if ($rowCount === 0) {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(500);
                $response->addMessage("Failed to retrieve task after update");
                $response->send();
                exit();
            }
    
            $taskArray = array();
    
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
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
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue updating task - please try again");
            $response->send();
            exit();
        }
    }
}

