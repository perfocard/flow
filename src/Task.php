<?php

namespace Perfocard\Flow;

class Task
{
    public static function for(string $taskClass): PendingTask
    {
        $task = app($taskClass);

        return new PendingTask($task);
    }
}
