<?php

namespace Perfocard\Flow;

class Callback
{
    public static function for(string $callbackClass): PendingCallback
    {
        return new PendingCallback($callbackClass);
    }
}
