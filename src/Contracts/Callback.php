<?php

namespace Perfocard\Flow\Contracts;

use Illuminate\Http\Request;
use Perfocard\Flow\Models\FlowModel;

interface Callback
{
    public function initial(Request $request): BackedEnum;

    public function processing(Request $request): BackedEnum;

    public function complete(Request $request): BackedEnum;

    public function failed(Request $request): BackedEnum;

    public function handle(Request $request): FlowModel;

    public function sanitizer(Request $request): ?string;
}
