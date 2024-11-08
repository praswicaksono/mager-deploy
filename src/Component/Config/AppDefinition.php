<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Build;
use App\Component\Config\Definition\Config;
use App\Component\Config\Definition\Proxy;
use Doctrine\Common\Collections\Collection;

final readonly class AppDefinition implements Definition
{
    /**
     * @param Collection<int, Config>|null $config
     * @param string[]                     $volumes
     * @param string[]                     $env
     */
    public function __construct(
        public string $name,
        public Build $build,
        public ?string $cmd = null,
        public ?Proxy $proxy = null,
        public ?Collection $config = null,
        public array $volumes = [],
        public array $env = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function parseToDockerVolume(string $namespace): array
    {
        $dockerMounts = [];
        foreach ($this->volumes as $volume) {
            [$src, $dest, $flag] = explode(':', $volume);

            if (file_exists($src) || is_dir($src)) {
                $dockerMounts[] = "type=bind,source={$src},destination={$dest},{$flag}";
            } else {
                $dockerMounts[] = "type=volume,source={$namespace}-{$src},destination={$dest}";
            }
        }

        return $dockerMounts;
    }

    /**
     * @return array<string, string|int|float>
     */
    public function resolveEnvValue(): array
    {
        $envs = [];
        foreach ($this->env as $name => $value) {
            if (str_starts_with($value, '${') && str_ends_with($value, '}')) {
                $envName = str_replace('${', '', $value);
                $envName = str_replace('}', '', $envName);
                [$envName, $defaultValue] = explode(':', $envName);

                $value = getenv($envName);
                if (false === $value && empty($defaultValue)) {
                    throw new \InvalidArgumentException("{$envName} not found in ENV and have no default value");
                }

                if (false === $value && '' !== $defaultValue) {
                    $envs[$envName] = $defaultValue;
                    continue;
                }

                $envs[] = "{$name}={$value}";
                continue;
            }

            $envs[] = "{$name}={$value}";
        }

        return $envs;
    }
}
