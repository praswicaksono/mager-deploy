<?php

declare(strict_types=1);

namespace App\Command;

use App\Helper\CommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'logs',
    description: 'Show logs for given service',
)]
final class LogsCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('namespace', InputArgument::REQUIRED);
        $this->addArgument('serviceName', InputArgument::REQUIRED);

        $this->addOption('follow', 'f', InputOption::VALUE_NONE, 'Stream logs until it cancelled');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $serviceName = $input->getArgument('serviceName');
        $follow = $input->getOption('follow') ?? false;

        $cmd = ['docker', 'service', 'logs', "{$namespace}-{$serviceName}"];
        if ($follow) {
            $cmd[] = '--follow';
        }

        $cmd = implode(' ', $cmd);

        $isRunning = runOnManager(static fn () => yield from CommandHelper::isServiceRunning($namespace, $serviceName), $namespace);
        if (!$isRunning) {
            $io->error("Service {$namespace}-{$serviceName} is not running");

            return Command::FAILURE;
        }

        runOnManager(static fn () => yield $cmd, $namespace, throwError: false, tty: true);

        return Command::SUCCESS;
    }
}
