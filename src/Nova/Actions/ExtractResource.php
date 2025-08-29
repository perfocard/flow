<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Perfocard\Flow\Compressor;

class ExtractResource extends Action
{
    public function name(): string
    {
        return __('Extract Resource');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $count = 0;
        foreach ($models as $model) {
            if ($model->compressed_at && ! $model->extracted_at) {
                Compressor::extract($model);
                $model->extracted_at = now();
                $model->save();
                $count++;
            }
        }

        return Action::message("Restored {$count} resources.");
    }
}
