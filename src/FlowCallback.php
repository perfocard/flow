<?php

namespace Perfocard\Flow;

use Illuminate\Http\Request;
use Perfocard\Flow\Contracts\Callback;
use Perfocard\Flow\Models\FlowModel;

abstract class FlowCallback implements Callback
{
    /**
     * Return the sanitizer class name to use for this callback, or null to
     * use the default behavior.
     */
    public function sanitizer(FlowModel $model, Request $request): ?string
    {
        return null;
    }
}
