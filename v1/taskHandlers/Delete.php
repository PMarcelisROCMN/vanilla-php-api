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
                new Response(false, 404, "Task not found");
                exit();
            }

            new Response(true, 200, "Task deleted");
            exit();
        } catch (PDOException $ex) {
            new Response(false, 500, "There was an issue deleting a task");
            exit();
        }
    }
}
