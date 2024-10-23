<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Data\Server as ServerConfig;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Helper\Traefik\Http;
use App\Component\Server\Task\AddProxyAutoConfiguration;
use App\Component\Server\Task\DockerNetworkCreate;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\DockerSwarmInit;
use App\Component\Server\Task\DockerVolumeCreate;
use App\Component\Server\Task\Param;
use App\Helper\Encryption;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

use function Amp\async;

#[AsCommand(
    name: 'mager:init',
    description: 'Add a short description for your command',
)]
final class MagerInitCommand extends Command
{
    private const string TRAEFIK_DASHBOARD_NAME = 'magerdashboard';
    private const string TRAEFIK_PAC_NAME = 'pac';

    private SymfonyStyle $io;

    private InputInterface $input;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'local',
            null,
            InputOption::VALUE_NONE,
            'Setup for mager for local development include proxy auto configuration',
        );

        $this->addOption(
            'debug',
            null,
            InputOption::VALUE_NONE,
            'Debug mode, will show all command output',
        );

        $this->addOption(
            'no-proxy',
            null,
            InputOption::VALUE_NONE,
            'Dont install proxy',
        );

        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_OPTIONAL,
            'Create namespace for the project',
        );
    }

    /**
     * @return array<int, string|bool>
     */
    private function initializeConfig(): array
    {
        $isLocal = $this->input->getOption('local') ?? false;
        $namespace = $this->input->getOption('namespace') ?? null;
        $debug = $this->input->getOption('debug') ?? false;
        $noProxy = $this->input->getOption('no-proxy') ?? false;
        $globalNetwork = Config::MAGER_GLOBAL_NETWORK;

        if (!$isLocal) {
            Assert::notEmpty($namespace, '--namespace must be a non-empty string');
        } else {
            $namespace = 'local';
        }

        // set default value for local server
        $proxyDashboard = 'dashboard.traefik.wip';
        $proxyUser = 'admin';
        $proxyPassword = 'admin123';
        $server['ip'] = '127.0.0.1';
        $server['ssh_user'] = null;
        $server['ssh_port'] = null;
        $server['ssh_key_path'] = null;
        $server['role'] = 'manager';

        if (!$isLocal) {
            $server['ip'] = $this->io->askQuestion(new Question('Please enter your server ip: ', '127.0.0.1'));
            $server['ssh_user'] = $this->io->askQuestion(new Question('Please enter manager ssh user:', 'root'));
            $server['ssh_port'] = (int) $this->io->askQuestion(new Question('Please enter manager ssh port:', 22));
            $server['ssh_key_path'] = $this->io->askQuestion(new Question('Please enter manager ssh key path:', '~/.ssh/id_rsa'));
            $proxyDashboard = $this->io->askQuestion(new Question('Please enter proxy dashboard url:', 'dashboard.traefik.wip'));
            $proxyUser = $this->io->askQuestion(new Question('Please enter proxy user:', 'admin'));
            $proxyPassword = $this->io->askQuestion(new Question('Please enter proxy password:', 'admin123'));
        }
        $servers = [];
        $servers[] = $server;

        $this->config->set("{$namespace}.servers", $servers);
        $this->config->set("{$namespace}.namespace", $namespace);
        $this->config->set("{$namespace}.debug", $debug);
        $this->config->set("{$namespace}.proxy_dashboard", $proxyDashboard);
        $this->config->set("{$namespace}.proxy_user", $proxyUser);
        $this->config->set("{$namespace}.proxy_password", Encryption::Htpasswd($proxyPassword));
        $this->config->set("{$namespace}.is_local", $isLocal);
        $this->config->set("{$namespace}.network", "{$namespace}-main");
        $this->config->set("{$namespace}.is_single_node", true);
        $this->config->set('global_network', $globalNetwork);

        $this->config->save();

        return [
            $namespace,
            $isLocal,
            $noProxy,
        ];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->io = $io = new SymfonyStyle($input, $output);

        [$namespace, $isLocal, $noProxy] = $this->initializeConfig();
        /** @var ServerConfig $serverConfig */
        $serverConfig = $this->config->getServers($namespace)->first();
        $debug = $this->config->isDebug($namespace);

        $executor = (new ExecutorFactory($this->config))($namespace);

        $server = Server::withExecutor($executor);

        $progress = new ProgressIndicator($io);
        $progress->start('Initializing Mager...');
        $showOutput = $server->showOutput($io, $debug, $progress);

        if (!$server->isDockerSwarmEnabled()) {
            async(function () use ($executor, $serverConfig, $showOutput) {
                $executor->run(
                    DockerSwarmInit::class,
                    [
                        Param::DOCKER_SWARM_MANAGER_IP->value => $serverConfig->ip,
                    ],
                    $showOutput,
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
                continueOnError: true,
            );

            $server->exec(
                DockerNetworkCreate::class,
                [
                    Param::DOCKER_NETWORK_CREATE_DRIVER->value => 'overlay',
                    Param::DOCKER_NETWORK_NAME->value => Config::MAGER_GLOBAL_NETWORK,
                ],
                $showOutput,
                continueOnError: true,
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
                    continueOnError: true,
                );
            })->await();

            if ($isLocal) {
                async(function () use ($server) {
                    $server->exec(
                        AddProxyAutoConfiguration::class,
                    );
                })->await();
            }

            async(function () use ($server, $namespace, $showOutput, $isLocal) {
                if (! $server->isProxyRunning($namespace)) {
                    $httpsSetup = [
                        '--entrypoints.web.http.redirections.entrypoint.to=websecure',
                        '--entryPoints.web.http.redirections.entrypoint.scheme=https',
                        '--entrypoints.websecure.address=:443',
                        '--entrypoints.websecure.http.tls.certresolver=mager',
                        '--certificatesresolvers.mager.acme.email=hello@praswicaksono.pw',
                        '--certificatesresolvers.mager.acme.tlschallenge=true',
                        '--certificatesresolvers.mager.acme.storage=/letsencrypt/acme.json',
                    ];

                    $command = [
                        '--api.dashboard=true',
                        '--api=true',
                        '--log.level=INFO',
                        '--accesslog=true',
                        "--providers.swarm.network={$namespace}-main",
                        '--providers.docker.exposedByDefault=false',
                        '--entrypoints.web.address=:80',
                        '--experimental.plugins.traefik-plugin-waeb.modulename=github.com/tomMoulard/traefik-plugin-waeb',
                        '--experimental.plugins.traefik-plugin-waeb.version=v1.0.1',
                    ];

                    if (! $isLocal) {
                        $command = array_merge($httpsSetup, $command);
                    }

                    $pac = [
                        "'traefik.http.routers.pac.rule=PathRegexp(`\.(pac)$`)'",
                        Http::port(self::TRAEFIK_PAC_NAME, 80),
                        Http::service(self::TRAEFIK_PAC_NAME, 'api@internal'),
                        Http::middleware(self::TRAEFIK_PAC_NAME, 'static-file'),
                        'traefik.http.middlewares.static-file.plugin.traefik-plugin-waeb.root=/var/www/html/',
                    ];

                    $label = [
                        'traefik.enable=true',
                        Http::host(self::TRAEFIK_DASHBOARD_NAME, $this->config->getProxyDashboard($namespace)),
                        Http::port(self::TRAEFIK_DASHBOARD_NAME, 80),
                        Http::middleware(self::TRAEFIK_DASHBOARD_NAME, 'dashboardauth'),
                        Http::service(self::TRAEFIK_DASHBOARD_NAME, 'api@internal'),
                        "traefik.http.middlewares.dashboardauth.basicauth.users={$this->config->getProxyUser($namespace)}:{$this->config->getProxyPassword($namespace)}",
                    ];

                    if ($isLocal) {
                        $label = array_merge($label, $pac);
                    }

                    $home = getenv('HOME');

                    $server->exec(
                        DockerServiceCreate::class,
                        [
                            Param::GLOBAL_NAMESPACE->value => $namespace,
                            Param::DOCKER_SERVICE_IMAGE->value => 'traefik:v3.2',
                            Param::DOCKER_SERVICE_NAME->value => 'mager_proxy',
                            Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-main", 'host'],
                            Param::DOCKER_SERVICE_CONSTRAINTS->value => ['node.role==manager'],
                            Param::DOCKER_SERVICE_PORT_PUBLISH->value => ['80:80', '443:443'],
                            Param::DOCKER_SERVICE_LABEL->value => $label,
                            Param::DOCKER_SERVICE_MOUNT->value => [
                                'type=bind,source=/var/run/docker.sock,destination=/var/run/docker.sock',
                                "type=bind,source={$home}/.mager/proxy.pac,destination=/var/www/html/proxy.pac",
                                "type=volume,source={$namespace}-proxy_letsencrypt,destination=/letsencrypt",
                            ],
                            Param::DOCKER_SERVICE_COMMAND->value => implode(' ', $command),
                        ],
                        $showOutput,
                    );
                }
            })->await();
        }

        $progress->finish('Initialization completed');
        $io->success('All Is Done ! Happy Developing !');

        return Command::SUCCESS;
    }
}
