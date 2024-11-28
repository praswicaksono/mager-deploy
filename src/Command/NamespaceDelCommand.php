<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
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
    private SymfonyStyle $io;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::REQUIRED, 'Namespace')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');

        $config = $this->config->get($namespace);
        Assert::notEmpty($config, "Namespace {$namespace} are not initialized, run mager namespace:add {$namespace}");

        $this->io->title("Removing namespace {$namespace} and associated services");

        return runOnManager(fn () => $this->removeAllAssociatedServices($namespace), $namespace);
    }

    private function removeAllAssociatedServices(string $namespace): \Generator
    {
        if (empty(yield 'docker node ls')) {
            $this->io->error('Docker swarm is not enabled yet');

            return Command::FAILURE;
        }

        yield 'Removing Services' => sprintf('docker service rm `docker service ls --format "{{.ID}}" --filter name=%s-`', $namespace);

        yield 'Removing Network' => sprintf('docker network rm `docker network ls --format "{{.ID}}" --filter name=%s-main`', $namespace);

        $home = getenv('HOME');

        yield 'Removing Capsule Definition' => "rm -rf {$home}/.mager/capsule/{$namespace}";

        $this->config->delete($namespace)->save();

        $this->io->success('Namespace has been deleted and services successfully removed');

        return Command::SUCCESS;
    }
}
