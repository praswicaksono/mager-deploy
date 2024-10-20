<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final readonly class Service
{
    /**
     * @param array<int, string>    $cmd
     * @param array<string, string> $env
     * @param array<int, string>    $volumes
     * @param array<int, string>    $beforeDeploy
     * @param array<int, string>    $afterDeploy
     */
    public function __construct(
        public string $name,
        public Proxy $proxy,
        public array $cmd = [],
        public array $env = [],
        public array $volumes = [],
        public array $beforeDeploy = [],
        public array $afterDeploy = [],
    ) {}

    /**
     * @return array<string, string|int|bool|array<string, string>>
     */
    public function toArray(): array
    {
        return [
            $this->name => [
                'proxy' =>$this->proxy->toArray(),
                'cmd' => $this->cmd,
                'env' => $this->env,
                'volumes' => $this->volumes,
                'before-deploy' => $this->beforeDeploy,
                'after_deploy' => $this->afterDeploy,
            ],
        ];
    }

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
}
