<?php

namespace Perfocard\Flow;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Perfocard\Flow\Contracts\HandledTask;

class PendingTask
{
    protected ?Model $model = null;

    public function __construct(
        protected HandledTask $task,
    ) {}

    public function on(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function dispatch()
    {
        if ($this->task instanceof HandledTask === false) {
            throw new Exception('Task must implement HandledTask interface.');
        }

        $this->model->setStatusAndSave(
            status: $this->task->processing($this->model),
        );

        $this->task->handle($this->model);

        $this->model->setStatusAndSave(
            status: $this->task->complete($this->model),
        );
    }
}
