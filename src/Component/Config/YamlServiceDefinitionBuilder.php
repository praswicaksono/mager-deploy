<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Build;
use App\Component\Config\Definition\Proxy;
use App\Component\Config\Definition\ProxyPort;
use App\Component\Config\Definition\Service;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Yaml\Yaml;

final class YamlServiceDefinitionBuilder implements DefinitionBuilder
{
    public function build(string $definitionPath = 'mager.yaml'): ServiceDefinition
    {
        $definitionArray = Yaml::parseFile($definitionPath);

        $buildConfig = $definitionArray['build'] ?? [];
        $build = new Build(
            context: $buildConfig['context'] ?? '.',
            dockerfile: $buildConfig['dockerfile'] ?? 'Dockerfile',
            target: $buildConfig['target'] ?? null,
        );

        $serviceCollection = new ArrayCollection();
        $services = $definitionArray['services'] ?? [];

        foreach ($services as $name => $service) {
            $proxy = new Proxy(
                rule: $service['proxy']['rule'],
                ports: new ArrayCollection(array_map(fn(string $port) => new ProxyPort($port), $service['proxy']['ports'] ?? [])),
            );

            $serviceCollection->add(
                new Service(
                    name: $name,
                    proxy: $proxy,
                    cmd: $service['cmd'] ?? [],
                    env: $service['env'] ?? [],
                    volumes: $service['volumes'] ?? [],
                    beforeDeploy: $service['beforeDeploy'] ?? [],
                    afterDeploy: $service['afterDeploy'] ?? [],
                ),
            );
        }

        return new ServiceDefinition($definitionArray['name'], $serviceCollection, $build);
    }
}
