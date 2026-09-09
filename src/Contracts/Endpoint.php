<?php

namespace Perfocard\Flow\Contracts;

use Illuminate\Http\Client\Response;
use Perfocard\Flow\Models\FlowModel;

interface Endpoint
{
    public function processing(): BackedEnum;

    public function complete(): BackedEnum;

    public function url($model): string;

    public function method($model): string;

    public function headers($model): array;

    public function buildPayload($model): array;

    public function processResponse(Response $response, $model): FlowModel;

    public function sanitizer(): ?string;
}
