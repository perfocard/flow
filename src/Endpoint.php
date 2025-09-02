<?php

namespace Perfocard\Flow;

class Endpoint
{
    public static function for(string $endpointClass): PendingEndpoint
    {
        $endpoint = app($endpointClass);

        return new PendingEndpoint($endpoint);
    }
}
