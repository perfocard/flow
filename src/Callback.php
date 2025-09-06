<?php

namespace Perfocard\Flow;

class Callback
{
    public static function for(string $callbackClass): PendingCallback
    {
        $callback = app($callbackClass);

        return new PendingCallback($callback);
    }
}
