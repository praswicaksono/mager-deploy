<?php

declare(strict_types=1);

namespace App\Command;

use App\Helper\CommandHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'scale',
    description: 'Scale application by spawning more container',
)]
final class ScaleCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::REQUIRED)
            ->addArgument('service', InputArgument::REQUIRED)
            ->addArgument('number', InputArgument::REQUIRED, 'Number of containers to spawn')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $namespace = $input->getArgument('namespace');
        $service = $input->getArgument('service');
        $number = (int) $input->getArgument('number');

        return runOnManager(static function () use ($namespace, $service, $number, $io): \Generator {
            if (0 === $number) {
                $io->error("Cant scale to 0, user 'mager service:del' to remove service");

                return Command::FAILURE;
            }

            if (!yield from CommandHelper::isServiceRunning($namespace, $service)) {
                $io->error("Service {$namespace}-{$service} is not running");

                return Command::FAILURE;
            }

            yield 'Scaling...' => "docker service scale {$namespace}-{$service}={$number}";

            return Command::SUCCESS;
        }, $namespace);
    }
}
