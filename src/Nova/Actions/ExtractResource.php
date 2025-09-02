<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Perfocard\Flow\Compressor;

class ExtractResource extends Action implements ShouldQueue
{
    use Queueable;

    public function name(): string
    {
        return __('Extract Resource');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        Compressor::extract($model);
        $model->extracted_at = now();
        $model->save();
    }
}
