<?php

namespace App\Command;

use App\Component\Config\ConfigFactory;
use App\Component\Config\ConfigInterface;
use App\Component\Config\Json;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\ExecutorInterface;
use App\Component\Server\LocalExecutor;
use App\Component\Server\RemoteExecutor;
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
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

use function Amp\async;

#[AsCommand(
    name: 'mager:init',
    description: 'Add a short description for your command',
)]
class MagerInitCommand extends Command
{
    public function __construct(
        private readonly ConfigInterface $config
    ) {
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
            'debug',
            null,
            InputOption::VALUE_NONE,
            'Debug mode, will show all command output'
        );

        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_OPTIONAL,
            'Namespace for multi tenant setup, default value will be "mager"'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $isLocal = $input->getOption('local') ?? false;
        $namespace = $input->getOption('namespace') ?? 'mager';
        $debug = $input->getOption('debug') ?? false;

        $onProgress = function (string $type, string $buffer) use ($io, $debug) {
            if ($debug) {
                if ($type === Process::ERR) {
                    $io->warning($buffer);
                } else {
                    $io->write($buffer);
                }
            }
        };

        $this->config->set('namespace', $namespace);
        $this->config->set('debug', $debug);
        $this->config->set('is_local', $isLocal);

        if (!$isLocal) {
            $managerIp = $io->askQuestion(new Question('Please enter your manager ip: ', '127.0.0.1'));
            $sshUser = $io->askQuestion(new Question('Please enter manager ssh user:', 'root'));
            $sshPort = $io->askQuestion(new Question('Please enter manager ssh port:', '22'));
            $sshKeyPath =$io->askQuestion(new Question('Please enter manager ssh key path:', '~/.ssh/id_rsa'));
            $this->config->set('remote.0.manager_ip', $managerIp);
            $this->config->set('remote.0.ssh_user', $sshUser);
            $this->config->set('remote.0.ssh_port', (int) $sshPort);
            $this->config->set('remote.0.ssh_key_path', $sshKeyPath);
        }

        $this->config->save();

        $executor = (new ExecutorFactory($this->config))();

        $helper = Server::withExecutor($executor);

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

        if ($isLocal) {
            $io->text('Setup proxy auto config ...');
            async(function () use ($helper, $namespace, $onProgress) {
                $helper->exec(
                    AddProxyAutoConfiguration::class
                );
            })->await();

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
