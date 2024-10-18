<?php

declare(strict_types=1);

namespace App\Component\Generator;

interface DockerfileGenerator
{
    public function generate(string $path = 'Dockerfile'): void;
}
