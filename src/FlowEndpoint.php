<?php

namespace Perfocard\Flow;

use Perfocard\Flow\Contracts\Endpoint;

/**
 * Base class for endpoints. The model is a constructor dependency of the
 * concrete class, so this base must not declare a constructor of its own.
 */
abstract class FlowEndpoint implements Endpoint
{
    /**
     * Return additional headers for the request.
     */
    public function headers(): array
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
