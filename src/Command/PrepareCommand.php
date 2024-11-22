<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Data\Server as ServerConfig;
use App\Component\TaskRunner\TaskBuilder\DockerCreateService;
use App\Helper\CommandHelper;
use App\Helper\ConfigHelper;
use App\Helper\HttpHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'prepare',
    description: 'Prepare server by installing required package',
)]
final class PrepareCommand extends Command
{
    private const string TRAEFIK_DASHBOARD_NAME = 'magerdashboard';

    private SymfonyStyle $io;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'namespace',
            InputArgument::REQUIRED,
            'Prepare servers that listed for given namespace',
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

        if (!$this->config->isLocal($namespace)) {
            if (Command::SUCCESS !== $this->getApplication()->doRun(new ArrayInput([
                'command' => 'provision',
                'namespace' => $namespace,
                'hosts' => [$serverConfig->hostname],
            ]), $output)) {
                return Command::FAILURE;
            }
        }

        $this->io->title('Preparing Docker Swarm');
        runOnManager(fn () => $this->prepareDockerSwarm($serverConfig), $namespace, throwError: false);

        $this->io->title('Preparing Docker Swarm Network');
        runOnManager(fn () => $this->prepareNetwork($namespace), $namespace, throwError: false);

        $this->io->title('Installing Proxy');
        runOnManager(fn () => $this->prepareProxy($namespace), $namespace);

        $this->io->success('Namespace Was Successfully Prepared');

        return Command::SUCCESS;
    }

    private function prepareDockerSwarm(ServerConfig $serverConfig): \Generator
    {
        $nodes = yield 'docker node ls --format {{.ID}}';

        if (empty($nodes)) {
            $this->io->section('Init docker swarm');

            yield 'Init Docker Swarm' => "docker swarm init --advertise-addr {$serverConfig->ip}";
        }
    }

    private function prepareNetwork(string $namespace): \Generator
    {
        $this->io->section('Creating namespace scoped network');

        yield 'Create Main Network' => "docker network create --scope=swarm -d overlay {$namespace}-main";
    }

    private function prepareProxy(string $namespace): \Generator
    {
        $isLocal = 'local' === $namespace;

        if (yield from CommandHelper::isServiceRunning($namespace, 'mager_proxy')) {
            $this->io->warning("Traefik proxy already running for {$namespace} namespace");

            return;
        }

        // Prepare traefik static config
        $httpsEntryPoint = [
            '--entrypoints.web.http.redirections.entrypoint.to=websecure',
            '--entryPoints.web.http.redirections.entrypoint.scheme=https',
            '--entrypoints.websecure.address=:443',
            '--entryPoints.websecure.http3',
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
            '--providers.file.filename=/var/traefik/dynamic.yaml',
        ];

        $staticConfig = array_merge($staticConfig, $httpsEntryPoint);

        if (!$isLocal) {
            $staticConfig = array_merge($staticConfig, $letsEncryptResolver);
        }

        $proxyDomain = $this->config->getProxyDashboard($namespace);
        $dynamicConfig = [
            'traefik.enable=true',
            HttpHelper::host(self::TRAEFIK_DASHBOARD_NAME, $proxyDomain),
            HttpHelper::port(self::TRAEFIK_DASHBOARD_NAME, 80),
            HttpHelper::middleware(self::TRAEFIK_DASHBOARD_NAME, 'dashboardauth'),
            HttpHelper::service(self::TRAEFIK_DASHBOARD_NAME, 'api@internal'),
            HttpHelper::tls(self::TRAEFIK_DASHBOARD_NAME),
            "traefik.http.middlewares.dashboardauth.basicauth.users={$this->config->getProxyUser($namespace)}:{$this->config->getProxyPassword($namespace)}",
        ];

        if (!$isLocal) {
            $dynamicConfig[] = HttpHelper::certResolver(self::TRAEFIK_DASHBOARD_NAME);
        }

        if ($isLocal) {
            $this->io->section('Generate local TLS certificates with mkcerts');

            yield from CommandHelper::generateTlsCertificateLocally($namespace, $proxyDomain);
            ConfigHelper::registerTLSCertificateLocally($proxyDomain);

            yield from CommandHelper::removeService($namespace, 'generate-tls-cert', 'replicated-job');
        }

        $home = getenv('HOME');

        $mounts = [
            "type=volume,source={$namespace}-proxy_letsencrypt,destination=/letsencrypt",
            'type=bind,source=/var/run/docker.sock,destination=/var/run/docker.sock',
        ];

        $localMounts = [
            "type=bind,source={$home}/.mager/certs,destination=/var/certs",
            "type=bind,source={$home}/.mager/dynamic.yaml,destination=/var/traefik/dynamic.yaml",
        ];

        if ($isLocal) {
            $mounts = array_merge($mounts, $localMounts);
        }

        $this->io->section('Installing traefik proxy');

        yield 'Deploying Traefik Proxy' => DockerCreateService::create($namespace, 'mager_proxy', 'traefik:v3.2')
            ->withNetworks(["{$namespace}-main", 'host'])
            ->withConstraints(['node.role==manager'])
            ->withPortPublish(['80:80', '443:443/tcp', '443:443/udp'])
            ->withLabels($dynamicConfig)
            ->withMounts($mounts)
            ->withCommand(implode(' ', $staticConfig))
        ;
    }
}
