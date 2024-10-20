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

use function Amp\async;
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
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Image name',
        );

        $this->addOption(
            'target',
            null,
            InputOption::VALUE_OPTIONAL,
            'Dockerfile build target',
        );

        $this->addOption(
            'file',
            null,
            InputOption::VALUE_REQUIRED,
            'Dockerfile path',
        );

        $this->addOption(
            'build',
            null,
            InputOption::VALUE_REQUIRED,
            'Version of current build',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $namespace = $input->getOption('namespace') ?? 'local';
        $version = $input->getOption('build') ?? 'latest';
        $name = $input->getOption('name') ?? null;
        $target = $input->getOption('target') ?? null;
        $dockerfile = $input->getOption('file') ?? 'Dockerfile';

        Assert::notEmpty($name, '--name must be a non-empty string');
        Assert::true($this->config->isNotEmpty(), "Namespace {$namespace} are not initialized, run mager mager:init --namespace {$namespace}");

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor);

        if (! exists($dockerfile)) {
            $io->error('Dockerfile does not exist');

            return Command::FAILURE;
        }

        $progress = new ProgressIndicator($output);
        $showOutput = $server->showOutput($io, $this->config->isDebug($namespace), $progress);

        $imageName = "{$namespace}-{$name}:{$version}";

        $progress->start("Building {$imageName}");
        async(function () use ($server, $imageName, $target, $showOutput) {
            $server->exec(
                DockerImageBuild::class,
                [
                    Param::DOCKER_IMAGE_TAG->value => $imageName,
                    Param::DOCKER_IMAGE_TARGET->value => $target,
                ],
                $showOutput,
            );
        })->await();

        $progress->finish('Image has been built');

        $io->success('Your image successfully built');

        return Command::SUCCESS;
    }
}
