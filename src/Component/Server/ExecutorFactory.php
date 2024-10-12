<?php

declare(strict_types=1);

namespace App\Component\Server;

use App\Component\Config\Config;
use Spatie\Ssh\Ssh;

final readonly class ExecutorFactory
{
    public function __construct(private Config $config) {}

    public function __invoke(string $namespace): ExecutorInterface
    {
        $config = $this->config->get("server.{$namespace}");

        $debug = $config['debug'];
        $executor = new LocalExecutor($debug);

        if (! $config['is_local']) {
            $ssh = Ssh::create(
                $config['ssh_user'],
                $config['manager_ip'],
            )->disablePasswordAuthentication()
                ->usePort($config['port'])
                ->usePrivateKey($config['ssh_key_path'])
                ->disableStrictHostKeyChecking()
                ->disablePasswordAuthentication()
                ->setTimeout(60 * 30);
            $executor = new RemoteExecutor($ssh, $debug);
        }

        return $executor;
    }
}
