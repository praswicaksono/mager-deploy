<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'dev',
    description: 'Preview deploy locally',
)]
final class DevCommand extends Command
{
    public function __construct(
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $deploy = new ArrayInput([
            'command' => 'deploy',
            'namespace' => 'local',
            '--dev' => null,
        ]);

        $this->getApplication()->doRun($deploy, $output);

        return Command::SUCCESS;
    }
}
