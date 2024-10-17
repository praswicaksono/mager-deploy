<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final class Build
{
    public function __construct(
        public ?string $target = null,
    ) {}
}
