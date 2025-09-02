<?php

namespace Perfocard\Flow\Nova\Filters;

use Illuminate\Http\Request;
use InvalidArgumentException;
use Laravel\Nova\Filters\Filter;

class EnumFilter extends Filter
{
    public $component = 'select-filter';

    protected string $column;

    protected string $enumClass;

    protected string $displayName;

    protected $labeler;

    public function __construct(string $name, string $column, string $enumClass, ?callable $labeler = null)
    {
        if (! enum_exists($enumClass)) {
            throw new InvalidArgumentException("{$enumClass} is not an enum.");
        }

        $this->displayName = $name;
        $this->column = $column;
        $this->enumClass = $enumClass;
        $this->labeler = $labeler;
    }

    public function name()
    {
        return $this->displayName;
    }

    public function apply(Request $request, $query, $value)
    {
        return $query->where($this->column, $value);
    }

    public function options(Request $request)
    {
        return $this->enumClass::map(true);
    }
}
