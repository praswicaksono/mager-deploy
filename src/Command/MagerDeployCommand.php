<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Definition\ProxyPort;
use App\Component\Config\Definition\Service;
use App\Component\Config\DefinitionBuilder;
use App\Component\Config\ServiceDefinition;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Helper\Traefik\Http;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\DockerServiceUpdate;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'mager:deploy',
    description: 'Deploy service based on mager.yaml',
)]
final class MagerDeployCommand extends Command
{
    public function __construct(
        private readonly Config $config,
        private readonly DefinitionBuilder $definitionBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            'Target namespace',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $namespace = $input->getOption('namespace') ?? 'local';
        /** @var ServiceDefinition $definition */
        $definition = $this->definitionBuilder->build();

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor);
        $progress = new ProgressIndicator($output);
        $progress->start('Starting deployment');
        $showOutput = $server->showOutput($io, $this->config->isDebug($namespace), $progress);
        $server->setOutputProgress($showOutput);

        $version = getenv('APP_VERSION');
        $version = $version === false ? 'latest' : $version;

        // Build target image
        $build = new ArrayInput([
            'command' => 'mager:build',
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

            // TODO: run before and after deploy hook
            $io->info("<info>Deploying {$imageName} ...</info>");

            if ($server->isServiceRunning("{$namespace}-{$service->name}")) {
                $this->updateService(
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    server: $server
                );
            } else {
                $this->createService(
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    service: $service,
                    server: $server
                );
            }
        }
        $progress->finish('Your application has been deployed.');

        $io->success('Your application has been deployed.');

        return Command::SUCCESS;
    }

    public function updateService(
        string $namespace,
        string $imageName,
        string $serviceName,
        Server $server
    ): void {
        $server->exec(
            DockerServiceUpdate::class,
            [
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_SERVICE_IMAGE->value => $imageName,
                Param::DOCKER_SERVICE_NAME->value => $serviceName
            ],
        );
    }

    public function createService(
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

        $network =[
            "{$namespace}-main",
            Config::MAGER_GLOBAL_NETWORK,
        ];

        $labels = [];

        // TODO: enable tls
        $labels[] = Http::rule("{$namespace}-{$serviceName}", $service->proxy->rule);
        /** @var ProxyPort $port */
        foreach ($service->proxy->ports as $port) {
            $labels[] = Http::port("{$namespace}-{$serviceName}", $port->getPort());
        }

        // TODO: add options for rolling update pause on failed and auto rollback
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
            ],
        );
    }
}
