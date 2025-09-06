<?php

namespace Perfocard\Flow\Contracts;

use Illuminate\Database\Eloquent\Relations\HasMany;

interface Probeable
{
    public function probes(): HasMany;
}
