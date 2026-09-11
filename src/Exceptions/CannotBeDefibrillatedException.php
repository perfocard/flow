<?php

namespace Perfocard\Flow\Exceptions;

use Exception;

/**
 * Thrown when the current status has no status to defibrillate into.
 */
class CannotBeDefibrillatedException extends Exception
{
    protected $message = 'Model has no status to defibrillate into.';
}
