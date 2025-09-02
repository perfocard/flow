<?php

namespace Perfocard\Flow;

use Illuminate\Support\Facades\Http;
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
            payload: json_encode($payload),
        );

        $method = $this->endpoint->method($this->model);

        $response = Http::withHeaders($this->endpoint->headers($this->model))
            ->$method($this->endpoint->url($this->model), $payload)
            ->throw();

        $this->model = $this->endpoint->processResponse($response, $this->model);

        $content = $response->json();

        if (is_null($content)) {
            $content = $response->body();
        }

        $this->model->setStatusAndSave(
            status: $this->endpoint->complete(),
            payload: $content,
        );
    }
}
