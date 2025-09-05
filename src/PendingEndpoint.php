<?php

namespace Perfocard\Flow;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Perfocard\Flow\Contracts\Endpoint;
use Perfocard\Flow\Models\FlowModel;
use Perfocard\Flow\Support\CurlFormatter;
use Perfocard\Flow\Support\ResponseFormatter;

class PendingEndpoint
{
    protected ?FlowModel $model = null;

    public function __construct(
        protected Endpoint $endpoint,
    ) {}

    public function on(FlowModel $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function dispatch()
    {
        $payload = $this->endpoint->buildPayload($this->model);

        $method = Str::upper($this->endpoint->method($this->model));

        $sanitizer = null;

        if ($this->endpoint->sanitizer()) {
            $sanitizerClass = $this->endpoint->sanitizer();
            $sanitizer = new $sanitizerClass;
        }

        $log = [
            'url' => $this->endpoint->url($this->model),
            'method' => $method,
            'headers' => $this->endpoint->headers($this->model),
            'payload' => $payload,
        ];

        $log = $sanitizer->apply($log);
        $log = CurlFormatter::build($log, $sanitizer?->maskChar());

        $this->model->setStatusAndSave(
            status: $this->endpoint->processing(),
            payload: $log,
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

        // $content = $response->json();

        // if (is_null($content)) {
        //     $content = $response->body();
        // } else {
        //     $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        // }

        $rawResponse = ResponseFormatter::build($response, $sanitizer);

        $this->model->setStatusAndSave(
            status: $this->endpoint->complete(),
            payload: $rawResponse,
        );
    }
}
