<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Data\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'exec',
    description: 'Execute command in running container',
)]
final class ExecCommand extends Command
{
    public function __construct(private readonly Config $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('namespace', InputArgument::REQUIRED);
        $this->addArgument('serviceName', InputArgument::REQUIRED);
        $this->addArgument('cmd', InputArgument::IS_ARRAY);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $namespace = $input->getArgument('namespace');
        $serviceName = $input->getArgument('serviceName');
        $command = implode(' ', $input->getArgument('cmd'));

        if ($this->config->isLocal($namespace)) {
            runLocally(function () use ($namespace, $serviceName, $command) {
                $cmd = <<<CMD
                docker exec -ti `docker ps -a --filter name={$namespace}-{$serviceName} --format '{{ .ID}}' | head -n1` {$command}
                CMD;

                return yield $cmd;
            }, tty: true);

            return Command::SUCCESS;
        }

        $info = trim(runOnManager(
            fn() => yield "docker service ps {$namespace}-{$serviceName} --format '{{.ID}}:{{.Name}}:{{.Node}}' | head -n1",
            $namespace,
        ));

        [, $name, $node] = explode(':', $info);

        $node = str_replace('.', '_', $node);
        $server = $this->config->get("{$namespace}.servers.{$node}");
        $server = new Server($server['ip'], $server['role'], $server['ssh_port'], $server['ssh_user'], $server['ssh_key_path']);

        $containerId = trim(runOnServer(fn() => yield "docker ps -a --filter name={$name} --format '{{ .ID}}'", $server));

        runOnServerWithTty("docker exec -ti {$containerId} {$command}", $server);

        return Command::SUCCESS;
    }
}
