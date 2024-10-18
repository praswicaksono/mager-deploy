<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final readonly class Service
{
    /**
     * @param array<int, string>    $command
     * @param array<string, string> $env
     * @param array<int, string>    $mounts
     * @param array<int, string>    $volumes
     * @param array<int, string>    $beforeDeploy
     * @param array<int, string>    $afterDeploy
     */
    public function __construct(
        public string $name,
        public int $port,
        public string $host,
        public array $command = [],
        public ?Build $build = null,
        public ?string $image = null,
        public bool $publish = true,
        public array $env = [],
        public array $mounts = [],
        public array $volumes = [],
        public array $beforeDeploy = [],
        public array $afterDeploy = [],
        public string $protocol = 'http',
    ) {}

    /**
     * @return array<string, string|int|bool|array<string, string>>
     */
    public function toArray(): array
    {
        return [
            $this->name => [
                'command' => $this->command,
                'protocol' => $this->protocol,
                'port' => $this->port,
                'host' => $this->host,
                'build' => [
                    'target' => $this->build?->target,
                ],
                'image' => $this->image,
                'publish' => $this->publish,
                'env' => $this->env,
                'mounts' => $this->mounts,
                'volumes' => $this->volumes,
                'before-deploy' => $this->beforeDeploy,
                'after_deploy' => $this->afterDeploy,
            ],
        ];
    }
}
