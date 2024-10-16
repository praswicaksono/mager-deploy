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
        $servers = $this->config->getServers();
        /** @var Server $target */
        $target = $servers->filter(function (Server $server) use ($role): bool {
            return $server->role === $role;
        })->first();
        $debug = $this->config->isDebug($namespace);
        $executor = new LocalExecutor($debug);

        if (! $this->config->isLocal()) {
            $ssh = Ssh::create(
                $target->user,
                $target->ip,
            )->disablePasswordAuthentication()
                ->usePort($target->port)
                ->usePrivateKey($target->keyPath)
                ->disableStrictHostKeyChecking()
                ->disablePasswordAuthentication()
                ->setTimeout(60 * 30);
            $executor = new RemoteExecutor($ssh, $debug);
        }

        return $executor;
    }
}
