<?php

namespace Perfocard\Flow\Listeners;

use Perfocard\Flow\Contracts\BackedEnum;
use Perfocard\Flow\Models\FlowModel;
use Throwable;

class ThrowableListener
{
    public static function saveException(FlowModel $resource, BackedEnum $status, Throwable $exception): void
    {
        $resource->setStatusAndSave(
            status: $status,
            payload: json_encode([
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
