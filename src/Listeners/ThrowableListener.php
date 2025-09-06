<?php

namespace Perfocard\Flow\Listeners;

use Perfocard\Flow\Contracts\BackedEnum;
use Perfocard\Flow\Models\FlowModel;
use Perfocard\Flow\Models\StatusType;
use Throwable;

class ThrowableListener
{
    public static function saveException(FlowModel $resource, BackedEnum $status, Throwable $exception): void
    {
        $resource->setStatusAndSave(
            status: $status,
            payload: (string) $exception,
            type: StatusType::EXCEPTION,
        );
    }
}
