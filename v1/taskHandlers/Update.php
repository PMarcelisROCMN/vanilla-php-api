<?php

class Update {

    private $writeDB;

    public function __construct($writeDB) {
        $this->writeDB = $writeDB;
    }

    public function updateTask($taskid, $returned_userid) {

        try {
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                new Response(false, 400, "Content type header is not set to JSON");
                exit();
            }

            $rawPatchData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPatchData)) {
                new Response(false, 400, "Request body is not valid JSON");
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
                new Response(false, 400, "No task fields provided");
                exit();
            }

            // Get the current task first
            $query = $this->writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                new Response(false, 404, "No task found to update");
                exit();
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
            }

            $query = $this->writeDB->prepare('UPDATE tbltasks SET ' . $queryFields . ' WHERE id = :taskid AND userid = :userid');

            if ($title_updated === true) {
                $task->setTitle($jsonData->title);
                $query->bindParam(':title', $jsonData->title, PDO::PARAM_STR);
            }

            if ($description_updated === true) {
                $task->setDescription($jsonData->description);
                $query->bindParam(':description', $jsonData->description, PDO::PARAM_STR);
            }

            if ($deadline_updated === true) {
                $task->setDeadline($jsonData->deadline);
                $query->bindParam(':deadline', $jsonData->deadline, PDO::PARAM_STR);
            }

            if ($completed_updated === true) {
                $task->setCompleted($jsonData->completed);
                $query->bindParam(':completed', $jsonData->completed, PDO::PARAM_STR);
            }

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                new Response(false, 400, "Task not updated - check submitted data for errors");
                exit();
            }

            $query = $this->writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                new Response(false, 500, "Failed to retrieve task after update");
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

            new Response(true, 201, "Task retrieved after update", $returnData);
            exit();
        } catch (TaskException $ex) {
            new Response(false, 400, $ex->getMessage());
            exit();
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            new Response(false, 500, "There was an issue updating task - please try again");
            exit();
        }
    }
}
