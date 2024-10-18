<?php

declare(strict_types=1);

namespace App\Component\Generator;

interface ProjectGuesser
{
    /**
     * @return array{0: class-string<DockerfileGenerator>, 1: class-string<ServiceDefinitionGenerator>}|null
     */
    public function guess(string $projectDir = '.'): ?array;
}
