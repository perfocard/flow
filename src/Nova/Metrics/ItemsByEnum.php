<?php

namespace Perfocard\Flow\Nova\Metrics;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;

class ItemsByEnum extends Partition
{
    public $name;

    protected string $modelClass;

    protected string $enumClass;

    protected string $enumColumn;

    public function __construct(string $name, string $modelClass, string $enumClass, string $enumColumn)
    {
        // parent::__construct($name);

        $this->name = $name;
        $this->modelClass = $modelClass;
        $this->enumClass = $enumClass;
        $this->enumColumn = $enumColumn;
    }

    /**
     * Calculate the value of the metric.
     *
     * @return mixed
     */
    public function calculate(NovaRequest $request)
    {
        return $this->count($request, $this->modelClass::orderBy('aggregate', 'desc'), $this->enumColumn)
            ->label(function ($value) {
                if (empty($value)) {
                    return __('Undefined');
                }

                return $this->enumClass::from($value)->label();
            });
    }
}
