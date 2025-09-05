<?php

namespace Perfocard\Flow;

use Perfocard\Flow\Contracts\HandledTask;
use Perfocard\Flow\Models\FlowModel;

class PendingTask
{
    protected ?FlowModel $model = null;

    public function __construct(
        protected HandledTask $task,
    ) {}

    public function on(FlowModel $model): self
    {
        $this->model = $model;

        return $this;
    }

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
