<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final readonly class Build
{
    public function __construct(
        public string $context,
        public string $dockerfile,
        public ?string $target = null,
    ) {}
}
