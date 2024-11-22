<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Data\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'worker:add',
    description: 'Add new worker server',
)]
final class WorkerAddCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('namespace', InputArgument::REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $config = $this->config->get($namespace);

        Assert::notEmpty($config, "Namespace {$namespace} are not initialized, run mager namespace:add {$namespace}");

        $server = new Server(
            ip: $this->io->askQuestion(new Question('Please enter your server ip: ', '127.0.0.1')),
            role: 'worker',
            port: (int) $this->io->askQuestion(new Question('Please enter manager ssh port:', 22)),
            user: $this->io->askQuestion(new Question('Please enter manager ssh user:', 'root')),
            keyPath: $this->io->askQuestion(new Question('Please enter manager ssh key path:', '~/.ssh/id_rsa')),
        );

        $hostname = runOnServer(fn () => yield 'hostname', $server);
        $server->hostname = str_replace('.', '_', trim($hostname));
        $this->config->set("{$namespace}.servers.{$server->hostname}", $server->toArray());
        $this->config->set("{$namespace}.is_single_node", false);
        $this->config->save();

        if (Command::SUCCESS !== $this->getApplication()->doRun(new ArrayInput([
            'command' => 'provision',
            'namespace' => $namespace,
            'hosts' => [$server->hostname],
        ]), $output)) {
            return Command::FAILURE;
        }

        $joinToken = trim(runOnManager(fn () => yield 'docker swarm join-token worker --quiet', $namespace));
        $managerIp = $this->config->getServers($namespace)->filter(fn (Server $server) => 'manager' === $server->role)->first()->ip;

        runOnServer(fn () => yield "docker swarm join --token {$joinToken} {$managerIp}:2377", $server);

        return Command::SUCCESS;
    }
}
