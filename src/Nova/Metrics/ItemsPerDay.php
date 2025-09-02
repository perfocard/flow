<?php

namespace Perfocard\Flow\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Nova;

class ItemsPerDay extends Trend
{
    protected string $modelClass;

    public $name;

    public function __construct(string $name, string $modelClass, ?string $component = null)
    {
        parent::__construct($component);

        $this->name = $name;
        $this->modelClass = $modelClass;
    }

    /**
     * Calculate the value of the metric.
     *
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->countByDays($request, $this->modelClass);
    }

    /**
     * Get the ranges available for the metric.
     *
     * @return array
     */
    public function ranges()
    {
        return [
            30 => Nova::__('30 Days'),
            60 => Nova::__('60 Days'),
            90 => Nova::__('90 Days'),
        ];
    }
}
