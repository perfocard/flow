<?php

namespace Perfocard\Flow\Contracts;

use Perfocard\Flow\Models\FlowModel;

interface HandledTask
{
    public function processing(FlowModel $model): BackedEnum;

    public function complete(FlowModel $model): BackedEnum;

    public function handle(FlowModel $model): FlowModel;
}
