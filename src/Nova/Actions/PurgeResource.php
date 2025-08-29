<?php

namespace Perfocard\Flow\Nova\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\ActionFields;

class PurgeResource extends Action
{
    public function name(): string
    {
        return __('Purge Resource');
    }

    public function handle(ActionFields $fields, Collection $models)
    {
        $count = 0;
        foreach ($models as $model) {
            if ($model->extracted_at) {
                $model->payload = null;
                $model->extracted_at = null;
                $model->save();
                $count++;
            }
        }

        return Action::message("Purged {$count} resources.");
    }
}
