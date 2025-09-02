<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Perfocard\Flow\Compressor;
use Throwable;

class CompressResource extends Action
{
    public function name(): string
    {
        return __('Compress Resource');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        if (! $model) {
            return Action::danger(__('No model provided.'));
        }

        try {
            Compressor::compress($model);
        } catch (Throwable $e) {
            return Action::danger(__('Compression failed.'));
        }

        return Action::message(__('Compressed resource.'));
    }
}
