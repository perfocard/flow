<?php

namespace Perfocard\Flow\Nova\Fields;

class DateTime extends \Laravel\Nova\Fields\DateTime
{
    /**
     * Create a new field.
     *
     * @param  string  $name
     * @param  string|null  $attribute
     * @param  mixed|null  $resolveCallback
     * @return void
     */
    public function __construct($name, $attribute = null, $resolveCallback = null)
    {
        parent::__construct($name, $attribute, $resolveCallback);
        $this->sortable()->filterable()->displayUsing(function ($value) {
            if (! $value) {
                return '-';
            }

            if ($value->isToday()) {
                return __('Today').$value->format(', H:i:s');
            }

            if ($value->isYesterday()) {
                return __('Yesterday').$value->format(', H:i:s');
            }

            return $value->format('d.m.Y, H:i:s');
        });
    }
}
