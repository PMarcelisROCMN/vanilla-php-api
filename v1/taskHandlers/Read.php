<?php

class Read {

    private $readDB;

    public function __construct($readDB){
        $this->readDB = $readDB;
    }

    public function getAllTasks($returned_userid){
        try {
            $query = $this->readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE userid = :userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

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
            $response->setHttpStatusCode(200);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue retrieving tasks");
            $response->send();
            exit();
        }
    }

    public function getSingularTask($taskid, $returned_userid){

        try {
            // :taskId is a placeholder for the actual value of $taskid that will be passed later to prevent SQL injection
            $query = $this->readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid AND userid = :userid');

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) {
                $response = new Response();
                $response->setSuccess(false);
                $response->setHttpStatusCode(404);
                $response->addMessage("Task not found");
                $response->send();
                exit();
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task(
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['deadline'],
                    $row['completed']
                );
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
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(400);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue retrieving a task");
            $response->send();
            exit();
        }
    }

    public function getCompleteTasks($completed, $returned_userid){
        try {

            $query = $this->readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE completed = :completed AND userid = :userid');
            $query->bindValue(':completed', $completed, PDO::PARAM_STR);
            $query->bindValue(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

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
            $response->setHttpStatusCode(200);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue retrieving tasks");
            $response->send();
            exit();
        }
    }

    public function getTasksPage($returned_userid, $page, $limitPerPage){
        try {
            $query = $this->readDB->prepare('SELECT COUNT(id) as totalNoOfTasks FROM tbltasks WHERE userid = :userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            // get the total number of tasks
            $tasksCount = intval($row['totalNoOfTasks']);

            // calculate the number of pages needed to display all tasks
            $numOfPages = ceil($tasksCount / $limitPerPage);

            if ($numOfPages == 0) {
                $numOfPages = 1;
            }

            if ($page > $numOfPages || $page == 0) {
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

            $query = $this->readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed 
            FROM tbltasks 
            WHERE userid = :userid
            LIMIT :pglimit OFFSET :offset');

            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();
            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
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
        } catch (TaskException $ex) {
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log("Database query error - " . $ex, 0);
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue retrieving tasks . $ex");
            $response->send();
            exit();
        }
    }
}