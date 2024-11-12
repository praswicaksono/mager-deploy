<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Build;
use App\Component\Config\Definition\Option;
use App\Component\Config\Definition\Proxy;
use App\Component\Config\Definition\ProxyPort;
use App\Component\Config\Definition\Service;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Yaml\Yaml;

use function Amp\File\exists;

final class YamlServiceDefinitionBuilder implements DefinitionBuilder
{
    public function build(string $definitionPath = 'mager.yaml', ?string $override = null): ServiceDefinition
    {
        $definitionArray = Yaml::parseFile($definitionPath);

        if (null !== $override) {
            if (exists("./mager.{$override}.yaml")) {
                $arrayOverride = Yaml::parseFile("mager.{$override}.yaml");
                $definitionArray = array_replace_recursive($definitionArray, $arrayOverride);
            }
        }

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
                host: $service['proxy']['host'] ?? 'localhost',
                ports: new ArrayCollection(array_map(fn(string $port) => new ProxyPort($port), $service['proxy']['ports'] ?? [])),
                rule: $service['proxy']['rule'] ?? null,
            );

            $option = new Option(
                limitCpu: $service['option']['limitCpu'] ?? null,
                limitMemory: $service['option']['limitMemory'] ?? null,
            );

            $serviceCollection->add(
                new Service(
                    name: $name,
                    proxy: $proxy,
                    cmd: $service['cmd'] ?? [],
                    env: $service['env'] ?? [],
                    volumes: $service['volumes'] ?? [],
                    beforeDeploy: $service['before_deploy'] ?? [],
                    afterDeploy: $service['after_deploy'] ?? [],
                    hosts: $service['hosts'] ?? [],
                    option: $option,
                    stopSignal: $service['stop_signal'] ?? null,
                ),
            );
        }

        return new ServiceDefinition($definitionArray['name'], $serviceCollection, $build);
    }
}
