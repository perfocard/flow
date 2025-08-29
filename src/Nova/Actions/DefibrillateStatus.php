<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

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
        $count = 0;
        $errors = [];
        foreach ($models as $model) {
            try {
                $model->defibrillate();
                $count++;
            } catch (\Throwable $e) {
                $errors[] = $model->getKey();
            }
        }

        if ($errors) {
            return Action::danger('Defibrillation failed for IDs: '.implode(', ', $errors));
        }

        return Action::message("Defibrillated {$count} models.");
    }
}
