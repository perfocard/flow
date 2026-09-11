<?php

namespace Perfocard\Flow;

use Illuminate\Http\Request;
use Perfocard\Flow\Contracts\Callback;
use Perfocard\Flow\Contracts\Idempotent;
use Perfocard\Flow\Models\FlowModel;
use Perfocard\Flow\Models\IdempotencyKey;
use Perfocard\Flow\Models\StatusType;
use Perfocard\Flow\Support\HandlerResolver;
use Perfocard\Flow\Support\HttpMessageFormatter;
use Perfocard\Flow\Support\Idempotency;
use Perfocard\Flow\Support\Sanitizer;
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
     * The resolved callback, built once the model is known.
     */
    protected ?Callback $callback = null;

    /**
     * Soft-return on invalid initial status instead of throwing.
     */
    protected bool $silent = false;

    /**
     * Create a new PendingCallback wrapper for the given callback class.
     */
    public function __construct(
        protected string $callbackClass,
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
     * Soft-return when the model is not in initial() status (e.g. provider retries).
     * Duplicate claims on a guarded status always soft-return regardless of this flag.
     * Exceptions from handle() are never swallowed.
     *
     * @return $this
     */
    public function silent(): self
    {
        $this->silent = true;

        return $this;
    }

    /**
     * Dispatch the callback: resolve the status this hit lands on, claim an
     * idempotency key only when that status is guarded, validate initial status,
     * record processing payload, call the handler, and record result or exception.
     *
     * complete($request) is resolved before handle(), so it must derive the
     * status from the request alone.
     *
     * @throws RuntimeException if request or model is missing or status invalid (unless silent)
     * @throws Throwable to bubble up any exception from the handler
     */
    public function dispatch()
    {
        if (! $this->request) {
            throw new RuntimeException('Request not set for PendingCallback');
        }

        if (! $this->model) {
            throw new RuntimeException('Model not set for PendingCallback');
        }

        $this->callback = HandlerResolver::resolve($this->callbackClass, $this->model);

        // The status this hit resolves to; decides whether dedupe applies at all
        $complete = $this->callback->complete($this->request);

        $claim = null;

        if ($this->callback instanceof Idempotent && Idempotency::guards($this->callback, $complete)) {
            $claim = Idempotency::claim($this->callback, $this->request, $complete);

            // Duplicate of a guarded status: nothing is written, nothing runs
            if ($claim === null) {
                return;
            }
        }

        if ($this->model->status != $this->callback->initial($this->request)) {
            if ($claim instanceof IdempotencyKey) {
                Idempotency::release($claim);
            }

            if ($this->silent) {
                return;
            }

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

        $sanitizer = $this->resolveSanitizer();

        if ($sanitizer) {
            $requestData = $sanitizer->apply($requestData);
        }

        // Serialize the request into a raw HTTP-like representation
        $payload = HttpMessageFormatter::buildRequest($requestData);

        // Mark resource as processing and save the serialized payload
        $this->model->setStatusAndSave(
            status: $this->callback->processing($this->request),
            payload: $payload,
            type: StatusType::CALLBACK,
        );

        try {
            // Execute the callback handler
            $this->model = $this->callback->handle($this->request);
        } catch (Throwable $exception) {
            if ($claim instanceof IdempotencyKey) {
                Idempotency::release($claim);
            }

            // On exception, record error status and exception payload, then rethrow
            $this->model->setStatusAndSave(
                status: $this->callback->failed($this->request),
                payload: (string) $exception,
                type: StatusType::EXCEPTION,
            );

            throw $exception;
        }

        // On success, set the status resolved up front
        $this->model->setStatusAndSave(
            status: $complete,
        );
    }

    /**
     * Resolve the callback sanitizer once for this dispatch.
     */
    protected function resolveSanitizer(): ?Sanitizer
    {
        $sanitizerClass = $this->callback->sanitizer($this->request);

        if (! $sanitizerClass) {
            return null;
        }

        return new $sanitizerClass;
    }
}
