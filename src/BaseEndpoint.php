<?php

namespace Perfocard\Flow;

use Perfocard\Flow\Contracts\Endpoint;
use Perfocard\Flow\Models\FlowModel;

abstract class BaseEndpoint implements Endpoint
{
    public function headers(FlowModel $model): array
    {
        return [];
    }

    public function mask(): array
    {
        return [];
    }
}
