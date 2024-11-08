<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\AppDefinition;
use App\Component\Config\Config;
use App\Component\Config\Definition\ProxyPort;
use App\Component\Config\YamlAppServiceDefinitionBuilder;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Helper;
use App\Component\Server\Helper\Traefik\Http;
use App\Component\Server\Result;
use App\Component\Server\Task\AppDownloadFromRemote;
use App\Component\Server\Task\DockerConfigCreate;
use App\Component\Server\Task\DockerConfigRemove;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Amp\File\exists;

#[AsCommand(
    name: 'app:install',
    description: 'Install 3rd party apps',
)]
final class AppInstallCommand extends Command
{
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
            'Deploy service to servers that listed for given namespace',
        );

        $this->addArgument(
            'url',
            InputArgument::REQUIRED,
            'URL package for 3rd party apps',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $namespace = $input->getArgument('namespace');
        $url = $input->getArgument('url');

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor, $io);

        /** @var Result<string> $result */
        $result = $server->exec(
            AppDownloadFromRemote::class,
            [
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::APP_URL->value => $url,
            ],
        );

        $cwd = $result->data;

        if (! exists($cwd . '/mager.yaml')) {
            throw new \InvalidArgumentException("Cant find 'mager.yaml' in '$cwd'");
        }

        $appDefinitionBuilder = new YamlAppServiceDefinitionBuilder();
        /** @var AppDefinition $appDefinition */
        $appDefinition = $appDefinitionBuilder->build($cwd . '/mager.yaml');

        if ($server->isServiceRunning("{$namespace}-app-{$appDefinition->name}")) {
            throw new \Exception('Apps already running for this namespace, use mager app:update to update it');
        }

        $constraint = ['node.role==worker'];
        if ($this->config->isSingleNode($namespace)) {
            $constraint = ['node.role==manager'];
        }

        $network = [
            "{$namespace}-main",
            Config::MAGER_GLOBAL_NETWORK,
        ];

        // Setup proxy if defined
        $labels = [];
        if ($appDefinition->proxy) {
            $labels[] = 'traefik.docker.lbswarm=true';
            $labels[] = 'traefik.enable=true';

            $rule = "Host(`{$appDefinition->proxy->host}`)";
            if (null !== $appDefinition->proxy->rule) {
                $rule = str_replace('{$host}', $appDefinition->proxy->host, $appDefinition->proxy->rule);
            }

            $fullServiceName = "{$namespace}-{$appDefinition->name}";
            $labels[] = Http::rule($fullServiceName, $rule);
            $labels[] = Http::tls($fullServiceName);

            /** @var ProxyPort $port */
            foreach ($appDefinition->proxy->ports as $port) {
                $labels[] = Http::port($fullServiceName, $port->getPort());
            }

            if (!$this->config->isLocal($namespace)) {
                $labels[] = Http::certResolver($fullServiceName);
            }
        }

        // Build image if defined
        $image = $appDefinition->build->resolveImageNameTagFromEnv();
        if (null === $appDefinition->build->image) {
            $build = new ArrayInput([
                'command' => 'build',
                '--namespace' => $namespace,
                '--target' => $appDefinition->build->target,
                '--file' => $cwd . '/' . $appDefinition->build->dockerfile,
                '--name' => $appDefinition->name,
                '--build' => 'latest',
            ]);

            $this->getApplication()->doRun($build, $output);
            $image = "{$namespace}-{$appDefinition->name}:latest";
        }

        // Create config
        $configs = [];
        foreach ($appDefinition->config as $config) {
            $configName = Helper::extractConfigNameFromPath($namespace, $config->srcPath);
            $server->exec(
                DockerConfigRemove::class,
                [
                    Param::DOCKER_CONFIG_NAME->value => $configName,
                ],
                continueOnError: true,
            );

            $server->exec(
                DockerConfigCreate::class,
                [
                    Param::GLOBAL_NAMESPACE->value => $namespace,
                    Param::DOCKER_CONFIG_FILE_PATH->value  => $cwd . '/' . $config->srcPath,
                ],
            );

            $configs[] = sprintf(
                'source=%s,target=%s,mode=0600',
                $configName,
                $config->destPath,
            );
        }


        $server->exec(
            DockerServiceCreate::class,
            [
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_SERVICE_IMAGE->value => $image,
                Param::DOCKER_SERVICE_NAME->value => 'app-' . $appDefinition->name,
                Param::DOCKER_SERVICE_REPLICAS->value => 1,
                Param::DOCKER_SERVICE_CONSTRAINTS->value => $constraint,
                Param::DOCKER_SERVICE_NETWORK->value => $network,
                Param::DOCKER_SERVICE_ENV->value => $appDefinition->resolveEnvValue(),
                Param::DOCKER_SERVICE_MOUNT->value => $appDefinition->parseToDockerVolume($namespace),
                Param::DOCKER_SERVICE_COMMAND->value => $appDefinition->cmd,
                Param::DOCKER_SERVICE_UPDATE_ORDER->value => 'start-first',
                Param::DOCKER_SERVICE_UPDATE_FAILURE_ACTION->value => 'rollback',
                Param::DOCKER_SERVICE_CONFIG->value => $configs,
                Param::DOCKER_SERVICE_LABEL->value => $labels,
            ],
        );

        $this->config->set("{$namespace}.apps.{$url}", true);
        $this->config->save();

        return Command::SUCCESS;
    }
}
