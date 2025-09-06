<?php

namespace Perfocard\Flow;

use Illuminate\Http\Request;
use Perfocard\Flow\Contracts\Callback;
use Perfocard\Flow\Models\FlowModel;
use Perfocard\Flow\Models\StatusType;
use Perfocard\Flow\Support\HttpMessageFormatter;
use RuntimeException;
use Throwable;

class PendingCallback
{
    /**
     * The Flow model being processed.
     */
    protected ?FlowModel $model = null;

    /**
     * The HTTP request associated with the callback.
     */
    protected ?Request $request = null;

    /**
     * Create a new PendingCallback wrapper for the given callback.
     */
    public function __construct(
        protected Callback $callback,
    ) {}

    /**
     * Set the model to operate on and return self for chaining.
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
     * Attach the HTTP request to the pending callback and return self.
     *
     * @param  $request  The incoming HTTP request
     * @return $this
     */
    public function withRequest(Request $request): self
    {
        $this->request = $request;

        return $this;
    }

    /**
     * Dispatch the callback: validate initial status, record processing
     * payload, call the handler, and record result or exception.
     *
     * @throws \RuntimeException if request or model is missing or status invalid
     * @throws \Throwable to bubble up any exception from the handler
     */
    public function dispatch()
    {
        if (! $this->request) {
            throw new RuntimeException('Request not set for PendingCallback');
        }

        if (! $this->model) {
            throw new RuntimeException('Model not set for PendingCallback');
        }

        if ($this->model->status != $this->callback->initial($this->model, $this->request)) {
            throw new RuntimeException('Cannot process callback: invalid status '.$this->model->status->name);
        }

        // Collect request data for logging/recording
        $requestData = [
            'method' => $this->request->getMethod(),
            'url' => $this->request->fullUrl(),
            'headers' => collect($this->request->headers->all())
                ->mapWithKeys(fn ($v, $k) => [ucwords($k, '-') => is_array($v) ? implode(', ', $v) : $v])
                ->all(),
            // payload: array of parsed input. Use $request->getContent() if you want raw body
            'payload' => $this->request->all(),
            // Laravel doesn't store HTTP version directly; default to 1.1
            'http_version' => '1.1',
        ];

        // If the callback provides a sanitizer class, apply it to the request data
        if ($this->callback->sanitizer($this->model, $this->request)) {
            $sanitizerClass = $this->callback->sanitizer($this->model, $this->request);
            $sanitizer = new $sanitizerClass;

            $requestData = $sanitizer->apply($requestData);
        }

        // Serialize the request into a raw HTTP-like representation
        $payload = HttpMessageFormatter::buildRequest($requestData);

        // Mark resource as processing and save the serialized payload
        $this->model->setStatusAndSave(
            status: $this->callback->processing($this->model, $this->request),
            payload: $payload,
            type: StatusType::CALLBACK,
        );

        try {
            // Execute the callback handler
            $model = $this->callback->handle($this->model, $this->request);
        } catch (Throwable $exception) {
            // On exception, record error status and exception payload, then rethrow
            $this->model->setStatusAndSave(
                status: $this->callback->failed($this->model, $this->request),
                payload: (string) $exception,
                type: StatusType::EXCEPTION,
            );

            throw $exception;
        }

        // On success, set the final status
        $model->setStatusAndSave(
            status: $this->callback->complete($this->model, $this->request),
        );
    }
}
