<?php

namespace Perfocard\Flow\Contracts;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Jsonable;

interface BackedEnum extends \BackedEnum, Arrayable, Htmlable, Jsonable {}
