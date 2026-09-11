<?php

namespace Perfocard\Flow\Contracts;

interface HasPage
{
    public function page(string $folder): string;
}
