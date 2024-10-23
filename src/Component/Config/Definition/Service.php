<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final readonly class Service
{
    /**
     * @param array<int, string> $cmd
     * @param array<int, string> $env
     * @param array<int, string> $volumes
     * @param array<int, string> $beforeDeploy
     * @param array<int, string> $afterDeploy
     */
    public function __construct(
        public string $name,
        public Proxy $proxy,
        public array $cmd = [],
        public array $env = [],
        public array $volumes = [],
        public array $beforeDeploy = [],
        public array $afterDeploy = [],
        public ?Option $option = null,
    ) {}

    /**
     * @return non-empty-array<string, array{
     *      'proxy': array{'rule': string, 'ports': array<int, string>},
     *      'cmd': array<int, string>,
     *      'env': array{}|array<int, string>,
     *      'volumes': array{}|array<int, string>,
     *      'before_deploy': array{}|array<int, string>,
     *      'after_deploy': array{}|array<int, string>,
     *      'option': array{'limit_cpu': float, 'limit_memory': string}
     *  }>
     */
    public function toArray(): array
    {
        return [
            $this->name => [
                'proxy' => $this->proxy->toArray(),
                'cmd' => $this->cmd,
                'env' => $this->env,
                'volumes' => $this->volumes,
                'before_deploy' => $this->beforeDeploy,
                'after_deploy' => $this->afterDeploy,
                'option' => $this->option->toArray(),
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
