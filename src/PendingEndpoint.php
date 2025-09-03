<?php

namespace Perfocard\Flow;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Perfocard\Flow\Contracts\Endpoint;
use Perfocard\Flow\Models\FlowModel;

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

        $this->model->setStatusAndSave(
            status: $this->endpoint->processing(),
            payload: json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        );

        $method = Str::upper($this->endpoint->method($this->model));

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

        $content = $response->json();

        if (is_null($content)) {
            $content = $response->body();
        } else {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $this->model->setStatusAndSave(
            status: $this->endpoint->complete(),
            payload: $content,
        );
    }
}
