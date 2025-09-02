<?php

namespace Perfocard\Flow\Observers;

use Perfocard\Flow\Contracts\ShouldCollectStatus;
use Perfocard\Flow\Contracts\ShouldDispatchEvents;
use Perfocard\Flow\Models\FlowModel;

/**
 * Observer for FlowModel status changes.
 *
 * Dispatches events when model status is created or updated.
 */
class ModelObserver
{
    /**
     * Handle the "created" event for the model.
     *
     * @return void
     */
    public function created(FlowModel $model)
    {
        if (! ($model instanceof ShouldCollectStatus) or ! ($model->status instanceof ShouldDispatchEvents)) {
            return;
        }

        $this->dispatchEvents($model);
    }

    /**
     * Handle the "updated" event for the model.
     *
     * @return void
     */
    public function updated(FlowModel $model)
    {
        if (! ($model instanceof ShouldCollectStatus) or ! ($model->status instanceof ShouldDispatchEvents)) {
            return;
        }

        if (property_exists($model, '__forceStatusEvents') && $model->__forceStatusEvents === true) {
            $this->dispatchEvents($model);

            return;
        }

        if ($model->status == $model->getOriginal('status')) {
            return;
        }

        $this->dispatchEvents($model);
    }

    /**
     * Dispatch all events associated with the model's status.
     *
     * @return void
     */
    protected function dispatchEvents(FlowModel $model)
    {
        foreach ($model->status->events() as $event) {
            $event::dispatch($model);
        }
    }
}
