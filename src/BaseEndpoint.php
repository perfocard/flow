<?php

namespace Perfocard\Flow;

use Perfocard\Flow\Contracts\Endpoint;
use Perfocard\Flow\Models\FlowModel;

abstract class BaseEndpoint implements Endpoint
{
    /**
     * Return additional headers for the request.
     */
    public function headers(FlowModel $model): array
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
