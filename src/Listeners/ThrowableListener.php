<?php

namespace Perfocard\Flow\Listeners;

use Perfocard\Flow\Contracts\BackedEnum;
use Throwable;

class ThrowableListener
{
    public static function saveException(object $event, string $key, BackedEnum $status, Throwable $exception): void
    {
        $event->{$key}->setStatusAndSave(
            status: $status,
            payload: json_encode([
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'trace' => $exception->getTrace(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );
    }
}
