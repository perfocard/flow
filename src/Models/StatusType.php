<?php

namespace Perfocard\Flow\Models;

use Perfocard\Flow\Contracts\BackedEnum;
use Perfocard\Flow\Traits\IsBackedEnum;

enum StatusType: int implements BackedEnum
{
    use IsBackedEnum;

    case REQUEST = 0;
    case RESPONSE = 1;
    case CALLBACK = 2;
    case EXCEPTION = 3;
    case PROBE = 4;

    /**
     * Get the displayable label of the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::REQUEST => __('Request'),
            self::RESPONSE => __('Response'),
            self::CALLBACK => __('Callback'),
            self::EXCEPTION => __('Exception'),
            self::PROBE => __('Probe'),
        };
    }
}
