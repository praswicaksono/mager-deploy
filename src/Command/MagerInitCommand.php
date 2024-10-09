<?php

namespace App\Command;

use App\Component\Server\LocalExecutor;
use App\Component\Server\Task\AddLocalHostFile;
use App\Component\Server\Task\DockerNetworkCreate;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\DockerSwarmInit;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $managerIp = '127.0.0.1';
        $namespace = 'local';

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
            $executor->run(DockerSwarmInit::class, [Param::DOCKER_SWARM_MANAGER_IP->value => $managerIp], $onProgress);
        }

        $io->text('Configuring Mager network...');
        $helper->exec(
            DockerNetworkCreate::class, [
                Param::DOCKER_NETWORK_CREATE_DRIVER->value => 'overlay',
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_NETWORK_NAME->value => 'mager',
            ],
            $onProgress,
            continueOnError: true
        );

        $io->text('Configuring Mager proxy ...');
        async(function () use ($helper, $namespace, $onProgress) {
            if (! $helper->isTraefikRunning($namespace)) {
                $helper->exec(
                    DockerServiceCreate::class, [
                    Param::DOCKER_SERVICE_IMAGE->value => 'traefik:v3.2',
                    Param::DOCKER_SERVICE_NAME->value => "{$namespace}-mager_traefik",
                    Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-mager", 'host'],
                    Param::DOCKER_SERVICE_CONSTRAINTS->value => ['node.role==manager'],
                    Param::DOCKER_SERVICE_PORT_PUBLISH->value => ['80:80', '443:443', '8080:8080'],
                    Param::DOCKER_SERVICE_MOUNT->value => ['type=bind,source=/var/run/docker.sock,destination=/var/run/docker.sock'],
                    Param::DOCKER_SERVICE_COMMAND->value => "--api.insecure=true --providers.swarm.network=local-mager",
                ],
                    $onProgress
                );
            }
        })->await();

        $io->text('Configuring Mager dnsq ...');
        async(function () use ($helper, $namespace, $onProgress) {
            if (! $helper->isDnsMasqRunning($namespace)) {
                $helper->exec(
                    DockerServiceCreate::class, [
                    Param::DOCKER_SERVICE_IMAGE->value => 'janeczku/go-dnsmasq:latest',
                    Param::DOCKER_SERVICE_NAME->value => "{$namespace}-mager_dns",
                    Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-mager"],
                    Param::DOCKER_SERVICE_CONSTRAINTS->value => ['node.role==manager'],
                    Param::DOCKER_SERVICE_PORT_PUBLISH->value => ['5353:53/tcp', '5353:53/udp'],
                    Param::DOCKER_SERVICE_MOUNT->value => ['type=bind,source=/home/jowy/.mager,destination=/root/.mager'],
                    Param::DOCKER_SERVICE_COMMAND->value => '--nameservers 8.8.8.8 --hostsfile /root/.mager/hosts',
                ],
                    $onProgress
                );
            }
        })->await();

        $io->text('Configuring Mager host file...');
        $helper->exec(
            AddLocalHostFile::class,
        );


        $io->success('All Is Done ! Happy Developing !');

        return Command::SUCCESS;
    }
}
