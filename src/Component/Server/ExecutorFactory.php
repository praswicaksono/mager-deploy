<?php

declare(strict_types=1);

namespace App\Component\Server;

use App\Component\Config\Config;
use App\Component\Config\Data\Server;
use Spatie\Ssh\Ssh;

final readonly class ExecutorFactory
{
    public function __construct(private Config $config) {}

    public function __invoke(string $namespace, string $role = 'manager'): ExecutorInterface
    {
        // TODO: Support multiple server in future
        $servers = $this->config->getServers();
        /** @var Server $target */
        $target = $servers->filter(function (Server $server) use ($role): bool {
            return $server->role === $role;
        })->first();

        $serverName = "{$namespace}-{$target->ip}";
        $executor = new LocalExecutor($serverName);

        if (! $this->config->isLocal($namespace)) {
            $ssh = Ssh::create(
                $target->user,
                $target->ip,
            )->disablePasswordAuthentication()
                ->usePort($target->port)
                ->usePrivateKey($target->keyPath)
                ->disableStrictHostKeyChecking()
                ->disablePasswordAuthentication()
                ->useMultiplexing("/tmp/{$target->user}-{$namespace}-%C")
                ->setTimeout(60 * 30);
            $executor = new RemoteExecutor($serverName, $ssh);
        }

        return $executor;
    }
}
