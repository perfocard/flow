<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Throwable;

class PurgeResource extends Action
{
    public function name(): string
    {
        return __('Purge Resource');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        if (! $model) {
            return Action::danger(__('No model provided.'));
        }

        if (! $model->extracted_at) {
            return Action::danger(__('Resource is not restored.'));
        }

        try {
            $model->payload = null;
            $model->extracted_at = null;
            $model->save();
        } catch (Throwable $e) {
            return Action::danger(__('Purge failed.'));
        }

        return Action::message(__('Resource purged.'));
    }
}
