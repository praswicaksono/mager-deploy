<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Data\Server as ServerConfig;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Helper\Traefik\Http;
use App\Component\Server\Task\AddProxyAutoConfiguration;
use App\Component\Server\Task\DockerNetworkCreate;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\DockerSwarmInit;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Component\Config\Config;

#[AsCommand(
    name: 'prepare',
    description: 'Prepare server by installing required package',
)]
final class PrepareCommand extends Command
{
    private const string TRAEFIK_DASHBOARD_NAME = 'magerdashboard';
    private const string TRAEFIK_PAC_NAME = 'pac';

    private SymfonyStyle $io;

    private Server $server;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'namespace',
            InputArgument::OPTIONAL,
            'Prepare servers that listed for given namespace',
            'local',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');

        // TODO: refactor later to support multinode preparation
        /** @var ServerConfig $serverConfig */
        $serverConfig = $this->config->getServers($namespace)->first();
        if (!$serverConfig instanceof ServerConfig) {
            $this->io->error("Namespace {$namespace} not found}");

            return Command::FAILURE;
        }

        $executor = (new ExecutorFactory($this->config))($namespace);

        $this->server = Server::withExecutor($executor, $this->io);

        // TODO: prepare specific OS task such as setup firewall, install docker, setup user, etc...
        $this->prepareDockerSwarm($serverConfig);
        $this->prepareNetwork($namespace);
        $this->prepareProxy($namespace);

        return Command::SUCCESS;
    }

    private function prepareDockerSwarm(ServerConfig $serverConfig): void
    {
        if (!$this->server->isDockerSwarmEnabled()) {
            $this->server->exec(
                DockerSwarmInit::class,
                [
                    Param::DOCKER_SWARM_MANAGER_IP->value => $serverConfig->ip,
                ],
            );
        }
    }

    private function prepareNetwork(string $namespace): void
    {
        $this->server->exec(
            DockerNetworkCreate::class,
            [
                Param::DOCKER_NETWORK_CREATE_DRIVER->value => 'overlay',
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_NETWORK_NAME->value => 'main',
            ],
            continueOnError: true,
        );

        $this->server->exec(
            DockerNetworkCreate::class,
            [
                Param::DOCKER_NETWORK_CREATE_DRIVER->value => 'overlay',
                Param::DOCKER_NETWORK_NAME->value => Config::MAGER_GLOBAL_NETWORK,
            ],
            continueOnError: true,
        );
    }

    private function prepareProxy(string $namespace): void
    {
        $isLocal = 'local' === $namespace;

        // Write auto proxy configuration if namespace is local for development purpose
        if ($isLocal) {
            $this->server->exec(
                AddProxyAutoConfiguration::class,
            );
        }

        if ($this->server->isProxyRunning($namespace)) {
            $this->io->warning("Traefik proxy already running for {$namespace} namespace");

            return;
        }

        // Prepare traefik static config
        $httpsEntryPoint = [
            '--entrypoints.web.http.redirections.entrypoint.to=websecure',
            '--entryPoints.web.http.redirections.entrypoint.scheme=https',
            '--entrypoints.websecure.address=:443',
            '--entryPoints.websecure.http3'
        ];

        $letsEncryptResolver = [
            '--entrypoints.websecure.http.tls.certresolver=mager',
            '--certificatesresolvers.mager.acme.email=hello@praswicaksono.pw',
            '--certificatesresolvers.mager.acme.tlschallenge=true',
            '--certificatesresolvers.mager.acme.storage=/letsencrypt/acme.json',
        ];

        $staticConfig = [
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

        if (!$isLocal) {
            $staticConfig = array_merge($staticConfig, $httpsEntryPoint, $letsEncryptResolver);
        }

        // Prepare traefik dynamic config for dashboard and proxy auto configuration
        $dynamicConfig = [
            'traefik.enable=true',
            Http::host(self::TRAEFIK_DASHBOARD_NAME, $this->config->getProxyDashboard($namespace)),
            Http::port(self::TRAEFIK_DASHBOARD_NAME, 80),
            Http::middleware(self::TRAEFIK_DASHBOARD_NAME, 'dashboardauth'),
            Http::service(self::TRAEFIK_DASHBOARD_NAME, 'api@internal'),
            "traefik.http.middlewares.dashboardauth.basicauth.users={$this->config->getProxyUser($namespace)}:{$this->config->getProxyPassword($namespace)}",
        ];

        $pacConfig = [
            "'traefik.http.routers.pac.rule=PathRegexp(`\.(pac)$`)'",
            Http::port(self::TRAEFIK_PAC_NAME, 80),
            Http::service(self::TRAEFIK_PAC_NAME, 'api@internal'),
            Http::middleware(self::TRAEFIK_PAC_NAME, 'static-file'),
            'traefik.http.middlewares.static-file.plugin.traefik-plugin-waeb.root=/var/www/html/',
        ];

        if ($isLocal) {
            $dynamicConfig = array_merge($dynamicConfig, $pacConfig);
        }

        $home = getenv('HOME');

        $this->server->exec(
            DockerServiceCreate::class,
            [
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_SERVICE_IMAGE->value => 'traefik:v3.2',
                Param::DOCKER_SERVICE_NAME->value => 'mager_proxy',
                Param::DOCKER_SERVICE_NETWORK->value => ["{$namespace}-main", 'host'],
                Param::DOCKER_SERVICE_CONSTRAINTS->value => ['node.role==manager'],
                Param::DOCKER_SERVICE_PORT_PUBLISH->value => ['80:80', '443:443/tcp', '443:443/udp'],
                Param::DOCKER_SERVICE_LABEL->value => $dynamicConfig,
                Param::DOCKER_SERVICE_MOUNT->value => [
                    'type=bind,source=/var/run/docker.sock,destination=/var/run/docker.sock',
                    "type=bind,source={$home}/.mager/proxy.pac,destination=/var/www/html/proxy.pac",
                    "type=volume,source={$namespace}-proxy_letsencrypt,destination=/letsencrypt",
                ],
                Param::DOCKER_SERVICE_COMMAND->value => implode(' ', $staticConfig),
            ],
        );
    }
}
