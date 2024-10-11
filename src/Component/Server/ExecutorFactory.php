<?php
declare(strict_types=1);

namespace App\Component\Server;

use App\Component\Config\Config;
use Spatie\Ssh\Ssh;

final readonly class ExecutorFactory
{
    public function __construct(private Config $config)
    {

    }

    public function __invoke(string $namespace): ExecutorInterface
    {
        $debug = $this->config->get("server.{$namespace}.debug", false);
        $executor = new LocalExecutor($debug);

        if (! $this->config->get("server.{$namespace}.is_local", true)) {
            $ssh = Ssh::create(
                $this->config->get("server.{$namespace}.ssh_user"),
                $this->config->get("server.{$namespace}.manager_ip")
            )->disablePasswordAuthentication()
                ->usePort($this->config->get("server.{$namespace}.ssh_port"))
                ->usePrivateKey($this->config->get("server.{$namespace}.ssh_key_path"))
                ->disableStrictHostKeyChecking()
                ->disablePasswordAuthentication()
                ->setTimeout(60 * 30);
            $executor = new RemoteExecutor($ssh, $debug);
        }

        return $executor;
    }
}