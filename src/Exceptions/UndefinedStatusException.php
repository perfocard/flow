<?php

namespace Perfocard\Flow\Exceptions;

use Exception;

/**
 * Thrown when model status is undefined.
 */
class UndefinedStatusException extends Exception
{
    protected $message = 'Undefined status.';
}
