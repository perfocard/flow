<?php

namespace Perfocard\Flow\Nova\Fields;

class Enum extends \Laravel\Nova\Fields\Select
{
    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string  $attribute
     * @param  mixed|null  $resolveCallback
     * @return void
     */
    public function __construct($name, $attribute, string $enum, $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);
        $this->options($enum::map())->displayUsingLabels();
    }
}
