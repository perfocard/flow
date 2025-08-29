<?php

namespace Perfocard\Flow\Traits;

trait Instantiatable
{
    /**
     * Універсальний метод для створення інстансу класу, який використовує цей трейт.
     *
     * @param  mixed  ...$params  Параметри, які передаються в конструктор.
     */
    public static function instance(...$params): static
    {
        return new static(...$params);
    }
}
