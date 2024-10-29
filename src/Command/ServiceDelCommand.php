<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Task\DockerServiceRemoveByServiceName;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'service:del',
    description: 'Delete and stop running service',
)]
final class ServiceDelCommand extends Command
{
    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::REQUIRED, 'Namespace name')
            ->addArgument('name', InputArgument::REQUIRED, 'Service name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $name = $input->getArgument('name');

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor, $io);

        $io->title('Deleting Service');
        $fullServiceName = "{$namespace}-{$name}";
        if (! $server->isServiceRunning($fullServiceName)) {
            $io->error("Service {$fullServiceName} is not running");
            return COMMAND::FAILURE;
        }

        $server->exec(
            DockerServiceRemoveByServiceName::class,
            [
                Param::DOCKER_SERVICE_NAME->value => $fullServiceName,
                Param::GLOBAL_PROGRESS_NAME->name => "Stopping and removing $fullServiceName",
            ],
        );

        $io->success('Your service has been deleted.');

        return Command::SUCCESS;
    }
}
