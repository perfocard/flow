<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Throwable;

class TouchResource extends Action
{
    /**
     * The displayable name of the action.
     */
    public function name(): string
    {
        return __('Touch');
    }

    /**
     * Perform the action on the given models.
     *
     * @return mixed
     */
    public function handle(ActionFields $fields, Collection $models)
    {
        $model = $models->first();

        if (! $model) {
            return Action::danger(__('No model provided.'));
        }

        try {
            $model->touch();
        } catch (Throwable $e) {
            return Action::danger(__('Touch failed.'));
        }

        return Action::message(__('Resource touched.'));
    }
}
