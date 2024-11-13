<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Helper\CommandHelper;
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
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (empty($this->config->get('local'))) {
            $addNamespace = new ArrayInput([
                'command' => 'namespace:add',
                'namespace' => 'local',
            ]);
            $this->getApplication()->doRun($addNamespace, $output);
        }

        if (! runLocally(fn() => CommandHelper::ensureServerArePrepared('local'), showProgress: false, throwError: false)) {
            $prepare = new ArrayInput([
                'command' => 'prepare',
                'namespace' => 'local',
            ]);
            $this->getApplication()->doRun($prepare, $output);
        }

        $deploy = new ArrayInput([
            'command' => 'deploy',
            'namespace' => 'local',
            '--dev' => null,
        ]);

        $this->getApplication()->doRun($deploy, $output);

        return Command::SUCCESS;
    }
}
