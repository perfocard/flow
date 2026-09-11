<?php

namespace Perfocard\Flow;

class Endpoint
{
    public static function for(string $endpointClass): PendingEndpoint
    {
        return new PendingEndpoint($endpointClass);
    }
}
