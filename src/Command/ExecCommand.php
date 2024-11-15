<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Data\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'exec',
    description: 'Execute command in running container',
    hidden: true,
)]
class ExecCommand extends Command
{
    public function __construct(private Config $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('namespace', InputArgument::REQUIRED);
        $this->addArgument('serviceName', InputArgument::REQUIRED);
        $this->addArgument('cmd', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $serviceName = $input->getArgument('serviceName');
        $command = $input->getArgument('cmd');

        if ($this->config->isLocal($namespace)) {
            $result = runLocally(function() use ($namespace, $serviceName, $command) {
                $cmd = <<<CMD
                docker exec -ti `docker ps -a --filter name={$namespace}-{$namespace} --format '{{ .ID}}'` {$command}
                CMD;

                return yield $cmd;
            }, tty: true);
            $io->writeln($result);
            return COmmand::SUCCESS;
        }

        $info = trim(runOnManager(
            fn() => yield "docker service ps {$namespace}-{$serviceName} --format '{{.ID}}:{{.Name}}:{{.Node}}'",
            $namespace
        ));

        [, $name, $node] = explode(':', $info);

        $server = $this->config->get("{$namespace}.servers.{$node}");
        $server = new Server($server['ip'], $server['role'], $server['ssh_port'], $server['ssh_user'], $server['ssh_key_path']);

        runOnServer(function() use ($name, $command) {
            $cmd = <<<CMD
                docker exec -ti `docker ps -a --filter name={$name} --format '{{ .ID}}'` {$command}
                CMD;

            return yield $cmd;
        }, $server);

        return Command::SUCCESS;
    }
}
