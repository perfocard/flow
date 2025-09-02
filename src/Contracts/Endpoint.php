<?php

namespace Perfocard\Flow\Contracts;

use Illuminate\Http\Client\Response;
use Perfocard\Flow\Models\FlowModel;

interface Endpoint
{
    public function processing(): BackedEnum;

    public function complete(): BackedEnum;

    public function url(FlowModel $model): string;

    public function method(FlowModel $model): string;

    public function headers(FlowModel $model): array;

    public function buildPayload(FlowModel $model): array;

    public function processResponse(Response $response, FlowModel $model): FlowModel;

    public function mask(): array;
}
