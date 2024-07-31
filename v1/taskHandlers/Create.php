<?php

class Create{

    private $readDB;
    private $writeDB;

    public function __construct($readDB, $writeDB){
        $this->readDB = $readDB;
        $this->writeDB = $writeDB;

    }

    public function createTask($returned_userid){
        try {

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
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

            if (!$jsonData = json_decode($rawPOSTData)) {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(400);
                $response->addMessage("Request body is not valid JSON");
                $response->send();
                exit();
            }

            if (!isset($jsonData->title) || !isset($jsonData->completed)) {
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

            $query = $this->writeDB->prepare('INSERT INTO tbltasks (title, description, deadline, completed) VALUES (:title, :description, STR_TO_DATE(:deadline, "%d/%m/%Y %H:%i"), :completed) WHERE userid = :userid');
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(500);
                $response->addMessage("Failed to create task");
                $response->send();
                exit();
            }

            // get last id of the item we just inserted
            $lastTaskID = $this->writeDB->lastInsertId();

            $query = $this->readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(500);
                $response->addMessage("Failed to retrieve task after creation");
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
            $response->addMessage("Task created");
            $response->setData($returnData);
            $response->send();
            exit();
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setSuccess(false);
            // 400 because the client data is wrong
            $response->setHttpStatusCode(400);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log('Datasbase querry error - ' . $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage('Failed to insert into database - check submitted data for errors: ' . $ex);
            $response->send();
            exit();
        }
    }
}