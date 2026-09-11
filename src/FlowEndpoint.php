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
     * Seconds to wait for the response. Override when the remote API is
     * genuinely slower or faster than the configured default.
     */
    public function timeout(): int
    {
        return (int) config('flow.endpoint.timeout', 30);
    }

    /**
     * Seconds to wait for the connection itself.
     */
    public function connectTimeout(): int
    {
        return (int) config('flow.endpoint.connect_timeout', 10);
    }

    /**
     * Whether a 4xx/5xx response should throw instead of reaching
     * processResponse(). Override with false only when the error body
     * carries a business verdict this endpoint has to read.
     */
    public function throw(): bool
    {
        return true;
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
