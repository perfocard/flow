<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Perfocard\Flow\Compressor;

class CompressResource extends Action
{
    public function name(): string
    {
        return __('Compress Resource');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $count = 0;
        foreach ($models as $model) {
            Compressor::compress($model);
        }

        return Action::message("Compressed {$count} resources.");
    }
}
