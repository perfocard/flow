<?php

namespace Perfocard\Flow\Exceptions;

use Exception;

/**
 * Thrown when the model handed to a Callback / Endpoint / Task does not match
 * the model type that handler typed on its constructor.
 */
class UnexpectedModelException extends Exception
{
    public function __construct(string $handler, string $expected, string $given)
    {
        parent::__construct("$handler expects $expected, $given given.");
    }
}
