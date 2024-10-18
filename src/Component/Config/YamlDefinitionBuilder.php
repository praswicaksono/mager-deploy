<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Build;
use App\Component\Config\Definition\Service;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Yaml\Yaml;

final class YamlDefinitionBuilder implements DefinitionBuilder
{
    public function build(string $definitionPath = 'mager.yaml'): Collection
    {
        $definitionArray = Yaml::parseFile($definitionPath);

        $collection = new ArrayCollection();
        $services = $definitionArray['services'] ?? [];
        foreach ($services as $name => $service) {
            $build = new Build();
            if (array_key_exists('build', $service)) {
                $target = $service['build']['target'] ?? null;
                $build->target = $target;
            }
            $collection->add(
                new Service(
                    $name,
                    $service['port'],
                    $service['host'],
                    $service['command'],
                    $build,
                    $service['image'] ?? null,
                    $service['publish'] ?? true,
                    $service['env'] ?? [],
                    $service['mounts'] ?? [],
                    $service['volumes'] ?? [],
                    $service['before_deploy'] ?? [],
                    $service['after_deploy'] ?? [],
                    $service['protocol'] ?? 'http',
                ),
            );
        }

        return $collection;
    }
}
