<?php

namespace Perfocard\Flow\Contracts;

use Illuminate\Http\Request;
use Perfocard\Flow\Models\FlowModel;

interface Callback
{
    public function initial(FlowModel $model, Request $request): BackedEnum;

    public function processing(FlowModel $model, Request $request): BackedEnum;

    public function complete(FlowModel $model, Request $request): BackedEnum;

    public function failed(FlowModel $model, Request $request): BackedEnum;

    public function handle(FlowModel $model, Request $request): FlowModel;

    public function sanitizer(FlowModel $model, Request $request): ?string;
}
