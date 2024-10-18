<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\DefinitionBuilder;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Helper\Traefik\Http;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
        $definitions = $this->definitionBuilder->build();

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor);
        $constraint = ['node.role==worker'];
        if ($this->config->isSingleNode($namespace)) {
            $constraint = ['node.role==manager'];
        }

        $version = getenv('APP_VERSION') ?? 'latest';

        foreach ($definitions as $name => $definition) {
            $imageName = "{$namespace}-{$name}:{$version}";

            // Build target image
            $build = new ArrayInput([
                'command' => 'mager:build',
                '--namespace' => $namespace,
                '--target' => $definition->build->target,
                '--name' => $name,
                '--version' => $version,
            ]);

            $this->getApplication()->doRun($build, $output);

            $io->info("Deploying {$imageName} ...");

            $network =[
                "{$namespace}-main",
                Config::MAGER_GLOBAL_NETWORK,
            ];

            $labels = [];
            if ($definition->publish) {
                $labels = Http::enable(
                    $imageName,
                    $definition->build->target,
                    $definition->port,
                );
            }

            $mounts = array_merge(
                $this->parseMounts($definition->mounts),
                $this->parseVolumes($namespace, $definition->volumes)
            );

            $server->exec(
                DockerServiceCreate::class,
                [
                    Param::GLOBAL_NAMESPACE->value => $namespace,
                    Param::DOCKER_SERVICE_IMAGE->value => $imageName,
                    Param::DOCKER_SERVICE_NAME->value => $imageName,
                    Param::DOCKER_SERVICE_REPLICAS->value => 1,
                    Param::DOCKER_SERVICE_CONSTRAINTS->value => $constraint,
                    Param::DOCKER_SERVICE_NETWORK->value => $network,
                    Param::DOCKER_SERVICE_ENV->value => $definition->env,
                    Param::DOCKER_SERVICE_LABEL->value => $labels,
                    Param::DOCKER_SERVICE_MOUNT->value => $mounts,
                ],
            );
        }

        $io->success('Your application has been deployed.');

        return Command::SUCCESS;
    }

    private function parseMounts(array $mounts): array
    {
        if (empty($mounts)) {
            return [];
        }

        $dockerMounts = [];
        foreach ($mounts as $mount) {
            [$target, $flag] = explode(',', $mount);
            [$src, $dest] = explode(':', $target);
            $dockerMounts[] = "type=bind,source={$src},destination={$dest},{$flag}";
        }

        return $dockerMounts;
    }

    private function parseVolumes(string $namespace, array $volumes): array
    {
        if (empty($volumes)) {
            return [];
        }

        $dockerVolumes = [];
        foreach ($volumes as $volume) {
            [$src, $dest] = explode(':', $volume);
            $dockerVolumes[] = "type=volume,source={$namespace}-{$src},destination={$dest}";
        }

        return $dockerVolumes;
    }
}
