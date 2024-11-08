<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Build;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Yaml\Yaml;
use App\Component\Config\Definition\Config as ConfigDefinition;

final class YamlAppServiceDefinitionBuilder implements DefinitionBuilder
{
    public function build(string $definitionPath = 'mager.yaml'): Definition
    {
        $definitionArray = Yaml::parseFile($definitionPath);

        $buildConfig = $definitionArray['mager']['build'] ?? [];
        $build = new Build(
            context: $buildConfig['context'] ?? '.',
            dockerfile: $buildConfig['dockerfile'] ?? 'Dockerfile',
            target: $buildConfig['target'] ?? null,
            image: $buildConfig['image'] ?? null,
        );

        $config = $definitionArray['mager']['config'] ?? [];
        $configCollection = new ArrayCollection();
        foreach ($config as $item) {
            $configCollection->add(ConfigDefinition::fromString($item));
        }

        return new AppDefinition(
            name: $definitionArray['mager']['name'],
            build: $build,
            cmd: $definitionArray['mager']['cmd'] ?? null,
            config: $configCollection,
            volumes: $definitionArray['mager']['volumes'] ?? [],
            env: $definitionArray['mager']['env'] ?? [],
        );
    }
}
