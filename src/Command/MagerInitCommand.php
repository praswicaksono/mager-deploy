<?php

namespace App\Command;

use App\Component\Server\LocalExecutor;
use App\Component\Server\Task\AddProxyAutoConfiguration;
use App\Component\Server\Task\DockerNetworkCreate;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\DockerSwarmInit;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

use function Amp\async;

#[AsCommand(
    name: 'mager:init',
    description: 'Add a short description for your command',
)]
class MagerInitCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'local',
            null,
            InputOption::VALUE_NONE,
            'Setup for mager for local development include proxy auto configuration'
        );

        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_OPTIONAL,
            'Namespace for multi tenant setup, default value will be "mager"'
        );

        $this->addOption(
            'manager-ip',
            null,
            InputOption::VALUE_OPTIONAL,
            'Manager IP for for docker swarm orchestrator, default value will be "127.0.0.1"'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $isLocal = (bool) $input->getOption('local');
        $namespace = $input->getOption('namespace') ?? 'mager';
        $managerIp = $input->getOption('manager-ip') ?? '127.0.0.1';

        $executor = new LocalExecutor(false);
        $helper = Server::withExecutor($executor);

        $onProgress = function (string $type, string $buffer) use ($io) {
            if ($type === Process::ERR) {
                $io->warning($buffer);
            } else {
                $io->write($buffer);
            }
        };

        if (!$helper->isDockerSwarmEnabled()) {
            async(function() use ($executor, $managerIp, $onProgress) {
                $executor->run(
                    DockerSwarmInit::class,
                    [
                        Param::DOCKER_SWARM_MANAGER_IP->value => $managerIp
                    ],
                    $onProgress
                );
            })->await();
        }

        $io->text('Configuring Mager network...');
        async(function () use ($helper, $namespace, $onProgress) {
            $helper->exec(
                DockerNetworkCreate::class, [
                Param::DOCKER_NETWORK_CREATE_DRIVER->value => 'overlay',
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_NETWORK_NAME->value => 'main',
            ],
                $onProgress,
                continueOnError: true
            );
        })->await();


        $io->text('Configuring Mager proxy ...');
        async(function () use ($helper, $namespace, $onProgress) {
            if (! $helper->isProxyRunning($namespace)) {
                $helper->exec(
                    DockerServiceCreate::class, [
                        Param::GLOBAL_NAMESPACE->value => $namespace,
                        Param::DOCKER_SERVICE_IMAGE->value => 'traefik:v3.2',
                        Param::DOCKER_SERVICE_NAME->value => 'mager_proxy',
                        Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-main", 'host'],
                        Param::DOCKER_SERVICE_CONSTRAINTS->value => ['node.role==manager'],
                        Param::DOCKER_SERVICE_PORT_PUBLISH->value => ['80:80', '443:443', '8080:8080'],
                        Param::DOCKER_SERVICE_MOUNT->value => ['type=bind,source=/var/run/docker.sock,destination=/var/run/docker.sock'],
                        Param::DOCKER_SERVICE_COMMAND->value => "--api.insecure=true --providers.swarm.network=local-mager",
                    ],
                    $onProgress
                );
            }
        })->await();

        $io->text('Setup proxy auto config ...');
        async(function () use ($helper, $namespace, $onProgress) {
            $helper->exec(
                AddProxyAutoConfiguration::class
            );
        })->await();

        if ($isLocal) {
            $io->text('Install local proxy for custom tld ...');
            async(function() use ($helper, $namespace, $onProgress) {
                if (! $helper->isProxyAutoConfigRunning($namespace)) {
                    $helper->exec(
                        DockerServiceCreate::class, [
                        Param::GLOBAL_NAMESPACE->value => $namespace,
                        Param::DOCKER_SERVICE_IMAGE->value => 'nginx',
                        Param::DOCKER_SERVICE_NAME->value => 'mager_pac',
                        Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-main", 'host'],
                        Param::DOCKER_SERVICE_CONSTRAINTS->value => ['node.role==manager'],
                        Param::DOCKER_SERVICE_PORT_PUBLISH->value => ['7000:80'],
                        Param::DOCKER_SERVICE_MOUNT->value => ['type=bind,source=/home/jowy/.mager/proxy.pac,destination=/usr/share/nginx/html/proxy.pac'],
                    ],
                        $onProgress
                    );
                }
            })->await();
        }


        $io->success('All Is Done ! Happy Developing !');

        return Command::SUCCESS;
    }
}
