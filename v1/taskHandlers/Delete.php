<?php

class Delete {

    private $writeDB;

    public function __construct($writeDB){
        $this->writeDB = $writeDB;
    }

    public function deleteTask($taskid, $returned_userid){
        try {
            $query = $this->writeDB->prepare('DELETE FROM tbltasks WHERE id = :taskid AND userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            // if rowcount is 0, then the task was not found
            if ($rowCount === 0) {
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
        } catch (PDOException $ex) {
            $response = new Response();
            $response->setSuccess(false);
            $response->setHttpStatusCode(500);
            $response->addMessage("There was an issue deleting a task");
            $response->send();
            exit();
        }
    }
}