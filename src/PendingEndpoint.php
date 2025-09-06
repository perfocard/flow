<?php

namespace Perfocard\Flow;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Perfocard\Flow\Contracts\Endpoint;
use Perfocard\Flow\Models\FlowModel;
use Perfocard\Flow\Models\StatusType;
use Perfocard\Flow\Support\CurlFormatter;
use Perfocard\Flow\Support\HttpMessageFormatter;

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
        $payload = $this->endpoint->buildPayload($this->model);

        $method = Str::upper($this->endpoint->method($this->model));

        $requestData = [
            'url' => $this->endpoint->url($this->model),
            'method' => $method,
            'headers' => $this->endpoint->headers($this->model),
            'payload' => $payload,
        ];

        if ($this->endpoint->sanitizer()) {
            $sanitizerClass = $this->endpoint->sanitizer();
            $sanitizer = new $sanitizerClass;

            $requestData = $sanitizer->apply($requestData);

            // Build a human-friendly curl-like command for logging
            $requestData = CurlFormatter::build($requestData, $sanitizer->maskChar());
        } else {
            // Build a human-friendly curl-like command for logging
            $requestData = CurlFormatter::build($requestData);
        }

        $this->model->setStatusAndSave(
            status: $this->endpoint->processing(),
            payload: $requestData,
            type: StatusType::REQUEST,
        );

        $options = [];

        if ($method == 'GET') {
            $options['query'] = $payload;
        } else {
            $options['json'] = $payload;
        }

        $response = Http::withHeaders($this->endpoint->headers($this->model))
            ->send($method, $this->endpoint->url($this->model), $options)
            ->throw();

        $this->model = $this->endpoint->processResponse($response, $this->model);

        $responseData = [
            'status' => $response->status(),
            'reason' => $response->reason(), // Laravel 11 provides the reason phrase
            'headers' => $response->headers(), // already in format ['Header'=>['v1','v2']]
            'payload' => $response->body(),    // raw body as string
            'http_version' => '1.1', // Laravel does not store HTTP version by default; assume 1.1
        ];

        if ($this->endpoint->sanitizer()) {
            $sanitizerClass = $this->endpoint->sanitizer();
            $sanitizer = new $sanitizerClass;

            $responseData = $sanitizer->apply($responseData);
        }

        $responseData = HttpMessageFormatter::buildResponse($responseData);

        $this->model->setStatusAndSave(
            status: $this->endpoint->complete(),
            payload: $responseData,
            type: StatusType::RESPONSE,
        );
    }
}
