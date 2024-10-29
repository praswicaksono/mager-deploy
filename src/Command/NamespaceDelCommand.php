<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Task\DockerServiceRemoveByNamespace;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'namespace:del',
    description: 'Remove namespace and its installed service',
)]
final class NamespaceDelCommand extends Command
{
    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::REQUIRED, 'Namespace name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');

        $config = $this->config->get($namespace);
        Assert::notEmpty($config, "Namespace {$namespace} are not exists");

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor, $io);

        $io->title("Removing namespace {$namespace} and associated services");

        if ($server->isDockerSwarmEnabled()) {
            $server->exec(
                DockerServiceRemoveByNamespace::class,
                [
                    Param::GLOBAL_NAMESPACE->value => $namespace,
                ],
                continueOnError: true,
            );
        }

        $this->config->delete($namespace)->save();

        $io->success('Namespace has been deleted and services successfully removed');

        return Command::SUCCESS;
    }
}
