<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\HttpKernel\KernelInterface;

final class ConsoleApplication extends Application
{
    private bool $commandsRegistered = false;

    public function __construct(
        private KernelInterface $kernel,
    ) {
        parent::__construct($this->kernel);

        $inputDefinition = $this->getDefinition();
        $inputDefinition->addOption(new InputOption('--env', '-e', InputOption::VALUE_REQUIRED, 'The Environment name.', $kernel->getEnvironment()));
        $inputDefinition->addOption(new InputOption('--no-debug', null, InputOption::VALUE_NONE, 'Switch off debug mode.'));
        $inputDefinition->addOption(new InputOption('--profile', null, InputOption::VALUE_NONE, 'Enables profiling (requires debug).'));
    }

    protected function registerCommands(): void
    {
        if ($this->commandsRegistered) {
            return;
        }

        $this->commandsRegistered = true;

        $this->kernel->boot();

        $container = $this->kernel->getContainer();

        /** @var CommandCollection $collection */
        $collection = $container->get('app.command_collection');
        foreach ($collection->commands as $command) {
            $this->add($command);
        }
    }
}
