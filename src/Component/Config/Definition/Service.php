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
        public array $command,
        public int $port,
        public string $host,
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
}
