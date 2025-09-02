<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Perfocard\Flow\Compressor;
use Throwable;

class ExtractResource extends Action
{
    public function name(): string
    {
        return __('Extract Resource');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        if (! $model) {
            return Action::danger(__('No model provided.'));
        }

        if (! $model->compressed_at) {
            return Action::danger(__('Resource is not compressed.'));
        }

        if ($model->extracted_at) {
            return Action::danger(__('Resource has already been restored.'));
        }

        try {
            Compressor::extract($model);
            $model->extracted_at = now();
            $model->save();
        } catch (Throwable $e) {
            return Action::danger(__('Extraction failed.'));
        }

        return Action::message(__('Restored resource.'));
    }
}
