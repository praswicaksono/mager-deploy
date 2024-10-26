<?php

namespace App\Command;

use App\Component\Config\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

use function Amp\File\createDirectory;
use function Amp\File\write;

#[AsCommand(
    name: 'namespace:dump',
    description: 'Dump namespace configuration to current directory',
)]
final class NamespaceDumpCommand extends Command
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

        $dir = '.mager';
        createDirectory($dir, 0755);
        write($dir . '/config.json', json_encode($config, JSON_PRETTY_PRINT));

        $io->success('Namespace configuration was successfully dumped!');

        return Command::SUCCESS;
    }
}