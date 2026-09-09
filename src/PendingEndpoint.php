<?php

namespace Perfocard\Flow;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Perfocard\Flow\Contracts\Endpoint;
use Perfocard\Flow\Models\FlowModel;
use Perfocard\Flow\Models\StatusType;
use Perfocard\Flow\Support\CurlFormatter;
use Perfocard\Flow\Support\HttpMessageFormatter;
use Perfocard\Flow\Support\Sanitizer;
use RuntimeException;
use Throwable;

class PendingEndpoint
{
    /**
     * The Flow model being processed.
     */
    protected ?FlowModel $model = null;

    /**
     * Create a new PendingEndpoint wrapper for the given endpoint.
     */
    public function __construct(
        protected Endpoint $endpoint,
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
     * Dispatch the endpoint: build payload, optionally sanitize, record
     * the outgoing request, execute the HTTP call, process and record the response.
     *
     * @throws \Throwable Rethrows HTTP or processing exceptions
     */
    public function dispatch()
    {
        if (! $this->model) {
            throw new RuntimeException('Model not set for PendingEndpoint');
        }

        $payload = $this->endpoint->buildPayload($this->model);
        $method = Str::upper($this->endpoint->method($this->model));
        $url = $this->endpoint->url($this->model);
        $headers = $this->endpoint->headers($this->model);

        // Non-GET requests are sent as JSON; ensure the logged curl matches.
        if ($method !== 'GET' && ! $this->hasHeader($headers, 'Content-Type')) {
            $headers['Content-Type'] = 'application/json';
        }

        $requestData = [
            'url' => $url,
            'method' => $method,
            'headers' => $headers,
            'payload' => $payload,
        ];

        $sanitizer = $this->resolveSanitizer();

        if ($sanitizer) {
            $requestData = $sanitizer->apply($requestData);
            $requestData = CurlFormatter::build($requestData, $sanitizer->maskChar());
        } else {
            $requestData = CurlFormatter::build($requestData);
        }

        $this->model->setStatusAndSave(
            status: $this->endpoint->processing(),
            payload: $requestData,
            type: StatusType::REQUEST,
        );

        $options = [];

        if ($method === 'GET') {
            $options['query'] = $payload;
        } else {
            $options['json'] = $payload;
        }

        try {
            $response = Http::withHeaders($headers)
                ->send($method, $url, $options)
                ->throw();
        } catch (Throwable $exception) {
            $this->recordException($exception);

            throw $exception;
        }

        try {
            $this->model = $this->endpoint->processResponse($response, $this->model);
        } catch (Throwable $exception) {
            $this->recordException($exception);

            throw $exception;
        }

        $responseData = [
            'status' => $response->status(),
            'reason' => $response->reason(),
            'headers' => $response->headers(),
            'payload' => $response->body(),
            'http_version' => '1.1',
        ];

        if ($sanitizer) {
            $responseData = $sanitizer->apply($responseData);
        }

        $responseData = HttpMessageFormatter::buildResponse($responseData);

        $this->model->setStatusAndSave(
            status: $this->endpoint->complete(),
            payload: $responseData,
            type: StatusType::RESPONSE,
        );
    }

    /**
     * Resolve the endpoint sanitizer once for this dispatch.
     */
    protected function resolveSanitizer(): ?Sanitizer
    {
        $sanitizerClass = $this->endpoint->sanitizer();

        if (! $sanitizerClass) {
            return null;
        }

        return new $sanitizerClass;
    }

    /**
     * Whether the header map already includes the given name (case-insensitive).
     */
    protected function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $key => $value) {
            if (is_int($key) && is_string($value) && str_contains($value, ':')) {
                [$headerName] = explode(':', $value, 2);
                if (strcasecmp(trim($headerName), $name) === 0) {
                    return true;
                }
            } elseif (is_string($key) && strcasecmp($key, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record an EXCEPTION status payload without changing the domain status
     * or re-dispatching status events. Listener failed() still owns ERROR.
     */
    protected function recordException(Throwable $exception): void
    {
        $payload = (string) $exception;

        if ($exception instanceof RequestException && $exception->response) {
            $payload = $exception->response->body() ?: $payload;
        }

        $this->model->statuses()->create([
            'status' => $this->endpoint->processing(),
            'payload' => $payload,
            'type' => StatusType::EXCEPTION,
        ]);
    }
}
