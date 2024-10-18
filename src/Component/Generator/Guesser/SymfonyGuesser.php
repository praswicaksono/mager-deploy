<?php

declare(strict_types=1);

namespace App\Component\Generator\Guesser;

use App\Component\Generator\Docker\SymfonyDockerGenerator;
use App\Component\Generator\ProjectGuesser;
use App\Component\Generator\ServiceDefinition\SymfonyDefinitionGenerator;

final class SymfonyGuesser implements ProjectGuesser
{
    public function guess(string $projectDir = '.'): ?array
    {
        if (file_exists($projectDir . '/composer.json')
            && file_exists($projectDir . '/config/packages/framework.yaml')
        ) {
            return [
                SymfonyDockerGenerator::class,
                SymfonyDefinitionGenerator::class,
            ];
        }

        return null;
    }
}
