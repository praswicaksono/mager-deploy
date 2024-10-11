<?php

namespace App\Command;

use App\Component\Server\LocalExecutor;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'mager:whoami',
    description: 'Add a short description for your command',
)]
class MagerWhoamiCommand extends Command
{

    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_OPTIONAL,
            'Create namespace for the project'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $namespace = 'local';

        $executor = new LocalExecutor();
        $helper = Server::withExecutor($executor);

        $onProgress = function(string $type, string $buffer) use ($io) {
            $io->write($buffer);
        };

        if (! $helper->isDockerSwarmEnabled()) {
            $io->error("Docker swarm was not enabled");
            return Command::FAILURE;
        }

        $executor->run(DockerServiceCreate::class, [
            Param::DOCKER_SERVICE_IMAGE->value => 'traefik/whoami',
            Param::DOCKER_SERVICE_NAME->value => "{$namespace}-whoami",
            Param::DOCKER_SERVICE_REPLICAS->value => 3,
            Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-mager"],
            Param::DOCKER_SERVICE_LABEL->value => [
                "'traefik.http.routers.local-whoami.rule=Host(`whoami.wip`)'",
                'traefik.http.services.local-whoami.loadbalancer.server.port=80'
            ]
        ], $onProgress);


        $io->success('Success');

        return Command::SUCCESS;
    }
}
