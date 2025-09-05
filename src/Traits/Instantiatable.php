<?php

namespace Perfocard\Flow\Traits;

trait Instantiatable
{
    public static function instance(...$params): static
    {
        /** @phpstan-ignore new.static */
        return new static(...$params);
    }
}
