<?php

namespace Perfocard\Flow;

use Perfocard\Flow\Contracts\HandledTask;
use Perfocard\Flow\Models\FlowModel;
use Perfocard\Flow\Support\HandlerResolver;
use RuntimeException;

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
     * The resolved task, built once the model is known.
     */
    protected ?HandledTask $task = null;

    /**
     * Create a new PendingTask instance for the given task class.
     */
    public function __construct(
        protected string $taskClass,
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
     *
     * @throws RuntimeException if the model is missing
     */
    public function dispatch()
    {
        if (! $this->model) {
            throw new RuntimeException('Model not set for PendingTask');
        }

        $this->task = HandlerResolver::resolve($this->taskClass, $this->model);

        $this->model->setStatusAndSave(
            status: $this->task->processing(),
        );

        $this->model = $this->task->handle();

        $this->model->setStatusAndSave(
            status: $this->task->complete(),
        );
    }
}
