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

        $buildConfig = $definitionArray['build'] ?? [];
        $build = new Build(
            context: $buildConfig['context'] ?? '.',
            dockerfile: $buildConfig['dockerfile'] ?? 'Dockerfile',
            target: $buildConfig['target'] ?? null,
            image: $buildConfig['image'] ?? null,
        );

        $config = $definitionArray['config'] ?? [];
        $configCollection = new ArrayCollection($config);
        foreach ($config as $item) {
            $configCollection->add(ConfigDefinition::fromString($item));
        }

        return new AppDefinition(
            name: $definitionArray['name'],
            build: $build,
            config: $configCollection,
            volumes: $definitionArray['volumes'] ?? [],
            env: $definitionArray['env'] ?? [],
        );
    }
}
