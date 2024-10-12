<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Task\DockerImageBuild;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

use function Amp\File\exists;

#[AsCommand(
    name: 'mager:build',
    description: 'Add a short description for your command',
)]
final class MagerBuildCommand extends Command
{
    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            'Create namespace for the project',
        );

        $this->addOption(
            'version',
            null,
            InputOption::VALUE_REQUIRED,
            'Version of current build',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $namespace = $input->getOption('namespace') ?? null;
        $version = $input->getOption('version') ?? 'latest';
        $config = $this->config->get("server.{$namespace}");

        Assert::notEmpty($namespace, '--namespace must be a non-empty string');
        Assert::notEmpty($config, "Namespace {$namespace} are not initialized, run mager mager:init --namespace {$namespace}");

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor);

        [$name, $cwd] = $server->getAppNameAndCwd();
        $dockerfile = $cwd . DIRECTORY_SEPARATOR . 'Dockerfile';

        if (! exists($dockerfile)) {
            $io->error('Dockerfile does not exist');

            return Command::FAILURE;
        }

        $progress = new ProgressIndicator($output);
        $showOutput = $server->showOutput($io, $config['debug'], $progress);

        $progress->start('Building image');
        $server->exec(
            DockerImageBuild::class,
            [
                Param::DOCKER_IMAGE_TAG->value => "mgr.la/{$namespace}-{$name}:{$version}",
            ],
            $showOutput,
        );
        $progress->finish('Image has been built');

        $io->success('Your image successfully built');

        return Command::SUCCESS;
    }
}
