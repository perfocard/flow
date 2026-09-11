<?php

namespace Perfocard\Flow\Contracts;

use Illuminate\Http\Client\Response;
use Perfocard\Flow\Models\FlowModel;

interface Endpoint
{
    public function processing(): BackedEnum;

    public function complete(): BackedEnum;

    public function url(): string;

    public function method(): string;

    public function headers(): array;

    public function timeout(): int;

    public function connectTimeout(): int;

    public function throw(): bool;

    public function buildPayload(): array;

    public function processResponse(Response $response): FlowModel;

    public function sanitizer(): ?string;
}
