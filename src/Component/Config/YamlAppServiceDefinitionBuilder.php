<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Build;
use App\Component\Config\Definition\Config as ConfigDefinition;
use App\Component\Config\Definition\Proxy;
use App\Component\Config\Definition\ProxyPort;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Yaml\Yaml;

final class YamlAppServiceDefinitionBuilder implements DefinitionBuilder
{
    public function build(string $definitionPath = 'mager.yaml', ?string $override = null): Definition
    {
        $definitionArray = Yaml::parseFile($definitionPath);
        $name = array_key_first($definitionArray);
        $definition = $definitionArray[$name];

        $buildConfig = $definition['build'] ?? [];
        $build = new Build(
            image: $buildConfig['image'],
            context: $buildConfig['context'] ?? '.',
            dockerfile: $buildConfig['dockerfile'] ?? 'Dockerfile',
            args: $buildConfig['args'] ?? [],
            target: $buildConfig['target'] ?? null,
        );

        $config = $definition['config'] ?? [];
        $configCollection = new ArrayCollection();
        foreach ($config as $item) {
            $configCollection->add(ConfigDefinition::fromString($item));
        }

        $proxy = new Proxy(
            host: $definition['proxy']['host'] ?? 'localhost',
            ports: new ArrayCollection(array_map(static fn (string $port) => new ProxyPort($port), $definition['proxy']['ports'] ?? [])),
            rule: $definition['proxy']['rule'] ?? null,
        );

        return new AppDefinition(
            name: $name,
            build: $build,
            cmd: $definitionArray[$name]['cmd'] ?? null,
            proxy: $proxy,
            config: $configCollection,
            volumes: $definitionArray[$name]['volumes'] ?? [],
            env: $definitionArray[$name]['env'] ?? [],
        );
    }
}
