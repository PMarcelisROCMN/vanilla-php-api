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
                new Response(false, 400, "Content type header is not set to JSON");
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
                new Response(false, 400, "Request body is not valid JSON");
                exit();
            }

            if (!isset($jsonData->title) || !isset($jsonData->completed)) {
                $messages = [];
                !isset($jsonData->title) ? $messages[] = "Title field is mandatory and must be provided" : null;
                !isset($jsonData->completed) ? $messages[] = "Completed field is mandatory and must be provided" : null;
                new Response(false, 400, $messages);
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

            $query = $this->writeDB->prepare('INSERT INTO tbltasks (userid, title, description, deadline, completed) VALUES (:userid, :title, :description, STR_TO_DATE(:deadline, "%d/%m/%Y %H:%i"), :completed)');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                new Response(false, 500, "Failed to create task");
                exit();
            }

            $lastTaskID = $this->writeDB->lastInsertId();

            $query = $this->readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                new Response(false, 500, "Failed to retrieve task after creation");
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

            new Response(true, 201, "Task created", $returnData);
            exit();
        } catch (TaskException $ex) {
            new Response(false, 400, $ex->getMessage());
            exit();
        } catch (PDOException $ex) {
            error_log('Database query error - ' . $ex, 0);
            new Response(false, 500, 'Failed to insert into database - check submitted data for errors: ' . $ex->getMessage());
            exit();
        }
    }
}
