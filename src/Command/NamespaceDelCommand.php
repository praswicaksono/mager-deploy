<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\TaskRunner\RunnerBuilder;
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
        Assert::notEmpty($config, "Namespace {$namespace} are not exists");

        $r = RunnerBuilder::create()
            ->withIO($this->io)
            ->withConfig($this->config)
            ->build($namespace);

        $this->io->title("Removing namespace {$namespace} and associated services");

        return $r->run($this->removeAllAssociatedServices($namespace));
    }

    private function removeAllAssociatedServices(string $namespace): \Generator
    {
        if (empty(yield 'docker node ls')) {
            $this->io->error('Docker swarm is not enabled yet');

            return Command::FAILURE;
        }

        yield sprintf('docker service rm `docker service ls --format "{{.ID}}" --filter name=%s-`', $namespace);
        yield sprintf('docker network rm `docker network ls --format "{{.ID}}" --filter name=%s-main`', $namespace);

        $this->config->delete($namespace)->save();

        $this->io->success('Namespace has been deleted and services successfully removed');

        return Command::SUCCESS;
    }
}
