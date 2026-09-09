<?php

namespace Perfocard\Flow;

use Perfocard\Flow\Contracts\Endpoint;

abstract class FlowEndpoint implements Endpoint
{
    /**
     * Return additional headers for the request.
     */
    public function headers($model): array
    {
        return [];
    }

    /**
     * Return the sanitizer class name to use for this endpoint, or null to
     * use the default behavior.
     */
    public function sanitizer(): ?string
    {
        return null;
    }
}
