<?php

require_once('../model/Task.php');

try
{
    $task = new Task(
        1,
        'Title 1',
        'Description 1',
        '01/01/2020 09:00',
        'N'
    );

    header('Content-type: application/json;charset=UTF-8');
    echo json_encode($task->returnTaskAsArray());

}catch(Exception $ex)
{
    echo 'Caught exception: ', $ex->getMessage(), "\n";
}