<?php

namespace App\Command;

use App\Component\Config\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'namespace:ls',
    description: 'List all namespaces',
)]
final class NamespaceLsCommand extends Command
{
    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $config = $this->config->all();

        $table = $io->createTable();
        $table->setHeaderTitle('Namespaces');
        $table->setHeaders(['Name', 'Is Single Node', 'Is Local']);
        foreach ($config as $name => $value) {
            if (!is_array($value)) {
                continue;
            }

            $table->addRow([$name, $value['is_single_node'] ? 'TRUE' : 'FALSE', $value['is_local'] ? 'TRUE' : 'FALSE']);
        }

        $table->render();

        return Command::SUCCESS;
    }
}
