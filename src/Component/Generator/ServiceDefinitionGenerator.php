<?php

declare(strict_types=1);

namespace App\Component\Generator;

interface ServiceDefinitionGenerator
{
    public function generate(string $path = 'mager.yaml'): void;
}
