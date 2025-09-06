<?php

namespace Perfocard\Flow;

use Perfocard\Flow\Contracts\Callback;

abstract class FlowCallback implements Callback
{
    /**
     * Return the sanitizer class name to use for this callback, or null to
     * use the default behavior.
     */
    public function sanitizer(): ?string
    {
        return null;
    }
}
