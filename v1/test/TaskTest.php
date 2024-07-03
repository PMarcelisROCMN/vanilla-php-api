<?php

require_once('../model/Task.php');
require_once('../model/Response.php');

try
{
    $task1 = new Task(
        1,
        'Title 1',
        'Description 1',
        '01/01/2020 09:00',
        'N'
    );

    $task2 = new Task(
        1,
        'Title 1',
        'Description 1',
        '01/01/2020 09:00',
        'N'
    );

    header('Content-type: application/json;charset=UTF-8');

    $returnData = array();

    $taskArray = array();
    $taskArray[] = $task1->returnTaskAsArray();
    // $taskArray[] = $task2->returnTaskAsArray();
    $returnData['rows_returned'] = 1;
    $returnData["tasks"] = $taskArray;

    $response = new Response();
    $response->setSuccess(true);
    $response->setHttpStatusCode(200);
    $response->addMessage("Task created");
    $response->setData($returnData);
    $response->send();
    // echo json_encode($task->returnTaskAsArray());

}catch(Exception $ex)
{
    echo 'Caught exception: ', $ex->getMessage(), "\n";
}