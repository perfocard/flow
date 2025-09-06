<?php

namespace Perfocard\Flow;

use Perfocard\Flow\Contracts\HandledTask;
use Perfocard\Flow\Models\FlowModel;

/**
 * Wrapper that executes a task against a FlowModel.
 *
 * It sets the processing status, invokes the task handler, and then sets
 * the completion status on the model.
 */
class PendingTask
{
    /**
     * The Flow model being processed.
     */
    protected ?FlowModel $model = null;

    /**
     * Create a new PendingTask instance.
     */
    public function __construct(
        protected HandledTask $task,
    ) {}

    /**
     * Attach the model to operate on and return self for chaining.
     *
     * @param  $model  The flow model to process
     * @return $this
     */
    public function on(FlowModel $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Execute the task: set processing status, run the handler, and set the
     * complete status afterwards.
     */
    public function dispatch()
    {
        $this->model->setStatusAndSave(
            status: $this->task->processing($this->model),
        );

        $this->task->handle($this->model);

        $this->model->setStatusAndSave(
            status: $this->task->complete($this->model),
        );
    }
}
