<?php

namespace Perfocard\Flow\Exceptions;

use Exception;

/**
 * Thrown when a model does not implement ShouldCollectStatus interface.
 */
class ShouldCollectStatusException extends Exception
{
    protected $message = 'Model has not implemented ShouldCollectStatus interface.';
}
