<?php

namespace Perfocard\Flow\Contracts;

use Illuminate\Http\Request;
use Perfocard\Flow\Models\FlowModel;

interface Callback
{
    public function initial($model, Request $request): BackedEnum;

    public function processing($model, Request $request): BackedEnum;

    public function complete($model, Request $request): BackedEnum;

    public function failed($model, Request $request): BackedEnum;

    public function handle($model, Request $request): FlowModel;

    public function sanitizer($model, Request $request): ?string;
}
