<?php

namespace Perfocard\Flow\Exceptions;

use Exception;

/**
 * Thrown when a model does not implement ShouldBeDefibrillated interface.
 */
class ShouldBeDefibrillatedException extends Exception
{
    protected $message = 'Model has not implemented ShouldBeDefibrillated interface.';
}
