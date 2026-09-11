<?php

namespace Perfocard\Flow\Contracts;

use Perfocard\Flow\Models\FlowModel;

interface HandledTask
{
    public function processing(): BackedEnum;

    public function complete(): BackedEnum;

    public function handle(): FlowModel;
}
