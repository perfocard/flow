<?php

namespace Perfocard\Flow\Contracts;

interface HasComponent
{
    public static function loading(): array;

    public static function failed(): array;
}
