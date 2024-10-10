<?php
declare(strict_types=1);

namespace App\Component\Server;

use App\Component\Config\ConfigInterface;
use Spatie\Ssh\Ssh;

final readonly class ExecutorFactory
{
    public function __construct(private ConfigInterface $config)
    {

    }

    public function __invoke(): ExecutorInterface
    {
        $executor = new LocalExecutor($this->config->get('debug', false));
        if (! $this->config->get('is_local', true)) {
            $ssh = Ssh::create(
                $this->config->get('remote.0.ssh_user'),
                $this->config->get('remote.0.manager_ip')
            )->disablePasswordAuthentication()
                ->usePort($this->config->get('remote.0.ssh_port'))
                ->usePrivateKey($this->config->get('remote.0.ssh_key_path'))
                ->disableStrictHostKeyChecking()
                ->disablePasswordAuthentication()
                ->setTimeout(60 * 30);
            $executor = new RemoteExecutor($ssh, $this->config->get('debug', false));
        }

        return $executor;
    }
}