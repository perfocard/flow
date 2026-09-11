<?php

namespace Perfocard\Flow;

use Illuminate\Http\Request;
use Perfocard\Flow\Contracts\Callback;

/**
 * Base class for callbacks. The model is a constructor dependency of the
 * concrete class, so this base must not declare a constructor of its own.
 */
abstract class FlowCallback implements Callback
{
    /**
     * Return the sanitizer class name to use for this callback, or null to
     * use the default behavior.
     */
    public function sanitizer(Request $request): ?string
    {
        return null;
    }
}
