<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Task\AddProxyAutoConfiguration;
use App\Component\Server\Task\DockerNetworkCreate;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\DockerSwarmInit;
use App\Component\Server\Task\DockerVolumeCreate;
use App\Component\Server\Task\Param;
use App\Helper\Encryption;
use App\Helper\Server;
use App\Helper\Traefik;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

use Webmozart\Assert\Assert;
use function Amp\async;

#[AsCommand(
    name: 'mager:init',
    description: 'Add a short description for your command',
)]
final class MagerInitCommand extends Command
{
    private const TRAEFIK_DASHBOARD_NAME = 'magerdashboard';

    public function __construct(
        private readonly Config $config
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
            'no-proxy',
            null,
            InputOption::VALUE_NONE,
            'Dont install proxy'
        );

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

        $isLocal = $input->getOption('local') ?? false;
        $namespace = $input->getOption('namespace') ?? null;
        $debug = $input->getOption('debug') ?? false;
        $noProxy = $input->getOption('no-proxy') ?? false;

        if (!$isLocal) {
            Assert::notEmpty($namespace, '--namespace must be a non-empty string');
        } else {
            $namespace = 'local';
        }

        // set default value for local server
        $managerIp = '127.0.0.1';
        $proxyDashboard = 'dashboard.traefik.wip';
        $proxyUser = 'admin';
        $proxyPassword = 'admin123';
        $sshUser = null;
        $sshPort = null;
        $sshKeyPath = null;

        if (!$isLocal) {
            $managerIp = $io->askQuestion(new Question('Please enter your manager ip: ', '127.0.0.1'));
            $sshUser = $io->askQuestion(new Question('Please enter manager ssh user:', 'root'));
            $sshPort = $io->askQuestion(new Question('Please enter manager ssh port:', '22'));
            $sshKeyPath = $io->askQuestion(new Question('Please enter manager ssh key path:', '~/.ssh/id_rsa'));
            $proxyDashboard = $io->askQuestion(new Question('Please enter proxy dashboard url:', 'dashboard.traefik.wip'));
            $proxyUser = $io->askQuestion(new Question('Please enter proxy user:', 'admin'));
            $proxyPassword = $io->askQuestion(new Question('Please enter proxy password:', 'admin123'));
        }

        $this->config->set("server.{$namespace}.manager_ip", $managerIp);
        $this->config->set("server.{$namespace}.ssh_user", $sshUser);
        $this->config->set("server.{$namespace}.ssh_port", (int) $sshPort);
        $this->config->set("server.{$namespace}.ssh_key_path", $sshKeyPath);
        $this->config->set("server.{$namespace}.namespace", $namespace);
        $this->config->set("server.{$namespace}.debug", $debug);
        $this->config->set("server.{$namespace}.proxy_dashboard", $proxyDashboard);
        $this->config->set("server.{$namespace}.proxy_user", $proxyUser);
        $this->config->set("server.{$namespace}.proxy_password", Encryption::Htpasswd($proxyPassword));
        $this->config->set("server.{$namespace}.is_local", $isLocal);
        $this->config->set("server.{$namespace}.network", "{$namespace}-main");

        $this->config->save();

        $executor = (new ExecutorFactory($this->config))($namespace);

        $server = Server::withExecutor($executor);

        $progress = new ProgressIndicator($io);
        $progress->start('Initializing Mager...');
        $showOutput = $server->showOutput($io, $debug, $progress);

        if (!$server->isDockerSwarmEnabled()) {
            async(function() use ($executor, $managerIp, $showOutput) {
                $executor->run(
                    DockerSwarmInit::class,
                    [
                        Param::DOCKER_SWARM_MANAGER_IP->value => $managerIp
                    ],
                    $showOutput
                );
            })->await();
        }

        async(function () use ($server, $namespace, $showOutput) {
            $server->exec(
                DockerNetworkCreate::class,
                [
                    Param::DOCKER_NETWORK_CREATE_DRIVER->value => 'overlay',
                    Param::GLOBAL_NAMESPACE->value => $namespace,
                    Param::DOCKER_NETWORK_NAME->value => 'main',
                ],
                $showOutput,
                continueOnError: true
            );
        })->await();

        if (! $noProxy) {
            async(function () use ($server, $namespace, $showOutput) {
                $server->exec(
                    DockerVolumeCreate::class,
                    [
                        Param::GLOBAL_NAMESPACE->value => $namespace,
                        Param::DOCKER_VOLUME_NAME->value => 'proxy_letsencrypt',
                    ],
                    $showOutput,
                    continueOnError: true
                );
            })->await();

            async(function () use ($server, $namespace, $showOutput, $isLocal) {
                if (! $server->isProxyRunning($namespace)) {
                    $sslSetup = [
                        '--entrypoints.web.http.redirections.entrypoint.to=websecure',
                        '--entryPoints.web.http.redirections.entrypoint.scheme=https',
                        '--entrypoints.websecure.address=:443',
                        '--entrypoints.websecure.http.tls.certresolver=mager',
                        '--certificatesresolvers.mager.acme.email=hello@praswicaksono.pw',
                        '--certificatesresolvers.mager.acme.tlschallenge=true',
                        '--certificatesresolvers.mager.acme.storage=/letsencrypt/acme.json'
                    ];

                    $command = [
                        '--api.dashboard=true',
                        '--api=true',
                        '--log.level=INFO',
                        '--accesslog=true',
                        "--providers.swarm.network={$namespace}-main",
                        '--providers.docker.exposedByDefault=false',
                        '--entrypoints.web.address=:80',
                    ];

                    if (! $isLocal) {
                        $command = array_merge($sslSetup, $command);
                    }

                    $server->exec(
                        DockerServiceCreate::class, [
                        Param::GLOBAL_NAMESPACE->value => $namespace,
                        Param::DOCKER_SERVICE_IMAGE->value => 'traefik:v3.2',
                        Param::DOCKER_SERVICE_NAME->value => 'mager_proxy',
                        Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-main", 'host'],
                        Param::DOCKER_SERVICE_CONSTRAINTS->value => ['node.role==manager'],
                        Param::DOCKER_SERVICE_PORT_PUBLISH->value => ['80:80', '443:443', '8080:8080'],
                        Param::DOCKER_SERVICE_LABEL->value => [
                            'traefik.enable=true',
                            Traefik::host(self::TRAEFIK_DASHBOARD_NAME, $this->config->get("server.{$namespace}.proxy_dashboard")),
                            Traefik::port(self::TRAEFIK_DASHBOARD_NAME, 80),
                            'traefik.http.routers.mydashboard.service=api@internal',
                            'traefik.http.routers.mydashboard.middlewares=dashboardauth',
                            "traefik.http.middlewares.dashboardauth.basicauth.users={$this->config->get("server.{$namespace}.proxy_user")}:{$this->config->get("server.{$namespace}.proxy_password")}",
                        ],
                        Param::DOCKER_SERVICE_MOUNT->value => [
                            'type=bind,source=/var/run/docker.sock,destination=/var/run/docker.sock',
                            "type=volume,source={$namespace}-proxy_letsencrypt,destination=/letsencrypt",
                        ],
                        Param::DOCKER_SERVICE_COMMAND->value => implode(' ', $command),
                    ],
                        $showOutput
                    );
                }
            })->await();

            if ($isLocal) {
                async(function () use ($server, $namespace, $showOutput) {
                    $server->exec(
                        AddProxyAutoConfiguration::class
                    );
                })->await();

                async(function() use ($server, $namespace, $showOutput) {
                    if (! $server->isProxyAutoConfigRunning($namespace)) {
                        $server->exec(
                            DockerServiceCreate::class, [
                            Param::GLOBAL_NAMESPACE->value => $namespace,
                            Param::DOCKER_SERVICE_IMAGE->value => 'nginx',
                            Param::DOCKER_SERVICE_NAME->value => 'mager_pac',
                            Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-main"],
                            Param::DOCKER_SERVICE_CONSTRAINTS->value => ['node.role==manager'],
                            Param::DOCKER_SERVICE_PORT_PUBLISH->value => ['7000:80'],
                            Param::DOCKER_SERVICE_LABEL->value => Traefik::enable(),
                            Param::DOCKER_SERVICE_MOUNT->value => ['type=bind,source=/home/jowy/.mager/proxy.pac,destination=/usr/share/nginx/html/proxy.pac'],
                        ],
                            $showOutput
                        );
                    }
                })->await();
            }
        }

        $progress->finish('Initialization completed');
        $io->success('All Is Done ! Happy Developing !');

        return Command::SUCCESS;
    }
}
