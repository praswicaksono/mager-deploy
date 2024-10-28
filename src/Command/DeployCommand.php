<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Definition\ProxyPort;
use App\Component\Config\Definition\Service;
use App\Component\Config\DefinitionBuilder;
use App\Component\Config\ServiceDefinition;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Helper\Traefik\Http;
use App\Component\Server\Task\DockerCleanupJob;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\DockerServiceUpdateImage;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'deploy',
    description: 'Deploy service to target server',
)]
final class DeployCommand extends Command
{
    public function __construct(
        private readonly Config $config,
        private readonly DefinitionBuilder $definitionBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'namespace',
            InputArgument::OPTIONAL,
            'Deploy service to servers that listed for given namespace',
            'local',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $namespace = $input->getArgument('namespace');
        /** @var ServiceDefinition $definition */
        $definition = $this->definitionBuilder->build();

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor, $io);

        $version = getenv('APP_VERSION');
        $version = false === $version ? 'latest' : $version;

        // Build target image
        $build = new ArrayInput([
            'command' => 'build',
            '--namespace' => $namespace,
            '--target' => $definition->build->target,
            '--file' => $definition->build->dockerfile,
            '--name' => $definition->name,
            '--build' => $version,
        ]);

        $imageName = "{$namespace}-{$definition->name}:{$version}";

        $this->getApplication()->doRun($build, $output);

        /**
         * @var Service $service
         */
        foreach ($definition->services as $service) {
            if ($this->config->get("{$namespace}.is_local")) {
                $io->info("Generate TLS Certificate for {$service->proxy->host}");
                $this->setupTls($namespace, $service, $server);
            }

            $io->info("Executing Before Deploy Hooks {$service->name} ...");
            foreach ($service->beforeDeploy as $job) {
                $this->runJob(
                    job: $job,
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    service: $service,
                    server: $server,
                );
            }

            $io->info("Deploying {$service->name} ...");
            if ($server->isServiceRunning("{$namespace}-{$service->name}")) {
                $this->updateService(
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    server: $server,
                );
            } else {
                $this->createService(
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    service: $service,
                    server: $server,
                );
            }

            $io->info("Executing After Deploy Hooks {$service->name} ...");
            foreach ($service->afterDeploy as $job) {
                $this->runJob(
                    job: $job,
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    service: $service,
                    server: $server,
                );
            }

            if (! empty($service->afterDeploy) || ! empty($service->beforeDeploy)) {
                $server->exec(DockerCleanupJob::class);
            }
        }

        $io->success('Your application has been deployed.');

        return Command::SUCCESS;
    }

    private function runJob(
        string $job,
        string $namespace,
        string $imageName,
        string $serviceName,
        Service $service,
        Server $server,
    ): void {
        $constraint = ['node.role==worker'];
        if ($this->config->isSingleNode($namespace)) {
            $constraint = ['node.role==manager'];
        }

        $network = [
            "{$namespace}-main",
            Config::MAGER_GLOBAL_NETWORK,
        ];

        $rand = bin2hex(random_bytes(8));
        $server->exec(
            DockerServiceCreate::class,
            [
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_SERVICE_IMAGE->value => $imageName,
                Param::DOCKER_SERVICE_NAME->value => "{$serviceName}-job-{$rand}",
                Param::DOCKER_SERVICE_MODE->value => 'replicated-job',
                Param::DOCKER_SERVICE_REPLICAS->value => 1,
                Param::DOCKER_SERVICE_COMMAND->value => $job,
                Param::DOCKER_SERVICE_CONSTRAINTS->value => $constraint,
                Param::DOCKER_SERVICE_NETWORK->value => $network,
                Param::DOCKER_SERVICE_ENV->value => $service->env,
            ],
        );
    }

    private function updateService(
        string $namespace,
        string $imageName,
        string $serviceName,
        Server $server,
    ): void {
        $server->exec(
            DockerServiceUpdateImage::class,
            [
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_SERVICE_IMAGE->value => $imageName,
                Param::DOCKER_SERVICE_NAME->value => $serviceName,
            ],
        );
    }

    private function createService(
        string $namespace,
        string $imageName,
        string $serviceName,
        Service $service,
        Server $server,
    ): void {

        $constraint = ['node.role==worker'];
        if ($this->config->isSingleNode($namespace)) {
            $constraint = ['node.role==manager'];
        }

        $network = [
            "{$namespace}-main",
            Config::MAGER_GLOBAL_NETWORK,
        ];

        $labels = [];

        // TODO: enable tls
        $labels[] = 'traefik.docker.lbswarm=true';
        $labels[] = 'traefik.enable=true';

        $rule = "Host(`{$service->proxy->host}`)";
        if (null !== $service->proxy->rule) {
            $rule = str_replace('{$host}', $service->proxy->host, $service->proxy->rule);
        }

        $labels[] = Http::rule("{$namespace}-{$serviceName}", $rule);
        /** @var ProxyPort $port */
        foreach ($service->proxy->ports as $port) {
            $labels[] = Http::port("{$namespace}-{$serviceName}", $port->getPort());
        }

        $server->exec(
            DockerServiceCreate::class,
            [
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_SERVICE_IMAGE->value => $imageName,
                Param::DOCKER_SERVICE_NAME->value => $serviceName,
                Param::DOCKER_SERVICE_REPLICAS->value => 1,
                Param::DOCKER_SERVICE_CONSTRAINTS->value => $constraint,
                Param::DOCKER_SERVICE_NETWORK->value => $network,
                Param::DOCKER_SERVICE_ENV->value => $service->env,
                Param::DOCKER_SERVICE_LABEL->value => $labels,
                Param::DOCKER_SERVICE_MOUNT->value => $service->parseToDockerVolume($namespace),
                Param::DOCKER_SERVICE_COMMAND->value => implode(' ', $service->cmd),
                Param::DOCKER_SERVICE_UPDATE_ORDER->value => 'start-first',
                Param::DOCKER_SERVICE_UPDATE_FAILURE_ACTION->value => 'rollback',
                Param::DOCKER_SERVICE_LIMIT_CPU->value => $service->option->limitCpu,
                Param::DOCKER_SERVICE_LIMIT_MEMORY->value => $service->option->limitMemory,
            ],
        );
    }

    public function setupTls(string $namespace, Service $service, Server $server): void
    {
        $server->generateTLSCertificate($namespace, $service->proxy->host);
    }
}
