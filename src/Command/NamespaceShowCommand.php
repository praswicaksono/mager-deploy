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
    name: 'namespace:show',
    description: 'Show detailed namespace configuration in JSON',
)]
final class NamespaceShowCommand extends Command
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

        $io->writeln(json_encode($config, JSON_PRETTY_PRINT));

        $io->title('Namespace Nodes');

        $io->writeln(runOnManager(fn() => yield 'docker node ls', $namespace));

        return Command::SUCCESS;
    }
}
