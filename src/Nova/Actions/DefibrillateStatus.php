<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;
use Throwable;

class DefibrillateStatus extends Action
{
    /**
     * The displayable name of the action.
     */
    public function name(): string
    {
        return __('Defibrillate');
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
            $model->defibrillate();
        } catch (Throwable $e) {
            return Action::danger(__('Defibrillation failed.'));
        }

        return Action::message(__('Defibrillated resource.'));
    }
}
