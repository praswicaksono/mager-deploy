<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

use App\Component\Config\Trait\EnvResolver;
use App\Component\Config\Trait\VolumeParser;

final readonly class Service
{
    use EnvResolver;
    use VolumeParser;

    /**
     * @param array<int, string>              $cmd
     * @param array<string, float|int|string> $env
     * @param array<int, string>              $volumes
     * @param array<int, string>              $executeOnce
     * @param array<int, string>              $beforeDeploy
     * @param array<int, string>              $afterDeploy
     * @param array<int, string>              $hosts
     */
    public function __construct(
        public string $name,
        public Proxy $proxy,
        public array $cmd = [],
        public array $env = [],
        public array $volumes = [],
        public array $executeOnce = [],
        public array $beforeDeploy = [],
        public array $afterDeploy = [],
        public array $hosts = [],
        public ?Option $option = null,
        public ?string $stopSignal = null,
    ) {}

    /**
     * @return non-empty-array<string, array{
     *      'proxy': array{'rule': string, 'ports': array<int, string>},
     *      'cmd': array<int, string>,
     *      'env': array{}|array<string, string|int|float>,
     *      'volumes': array{}|array<int, string>,
     *      'execute_once': array{}|array<int, string>,
     *      'before_deploy': array{}|array<int, string>,
     *      'after_deploy': array{}|array<int, string>,
     *      'hosts': array{}|array<int, string>,
     *      'option': array{'limit_cpu': float, 'limit_memory': string},
     *      'stop_signal': string|null
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
                'execute_once' => $this->executeOnce,
                'before_deploy' => $this->beforeDeploy,
                'after_deploy' => $this->afterDeploy,
                'hosts' => $this->hosts,
                'option' => $this->option->toArray(),
                'stop_signal' => $this->stopSignal,
            ],
        ];
    }
}
