<?php

namespace Perfocard\Flow\Contracts;

interface ShouldDispatchEvents
{
    public function events(): array;
}
