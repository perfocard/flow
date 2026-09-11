<?php

namespace Perfocard\Flow;

class Task
{
    public static function for(string $taskClass): PendingTask
    {
        return new PendingTask($taskClass);
    }
}
