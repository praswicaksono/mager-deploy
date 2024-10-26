<?php

declare(strict_types=1);

namespace App;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\HttpKernel\KernelInterface;

final class ConsoleApplication extends Application
{
    private bool $commandsRegistered = false;

    public function __construct(
        private KernelInterface $kernel,
    ) {
        parent::__construct($this->kernel);
    }

    protected function registerCommands(): void
    {
        if ($this->commandsRegistered) {
            return;
        }

        $definition = $this->getDefinition();
        $definition->setOptions();

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
