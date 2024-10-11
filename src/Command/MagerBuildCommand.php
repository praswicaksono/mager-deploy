<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\ExecutorInterface;
use App\Component\Server\Task\DockerImageBuild;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Amp\File\exists;

#[AsCommand(
    name: 'mager:build',
    description: 'Add a short description for your command',
)]
final class MagerBuildCommand extends Command
{
    public function __construct(
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $cwd = getcwd();
        $cwdArr = explode('/', $cwd);

        $name = end($cwdArr);
        $dockerfile = $cwd . DIRECTORY_SEPARATOR . 'Dockerfile';

        if (! exists($dockerfile)) {
            $io->error('Dockerfile does not exist');
        }

        $namespace = 'local';
        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor);

        $io->info('Building image ...');
        $server->exec(
            DockerImageBuild::class,
            [
                Param::DOCKER_IMAGE_TAG->value => "mgr.la/{$namespace}-{$name}"
            ]
        );

        $io->success('Your image successfully built');

        return Command::SUCCESS;
    }
}
