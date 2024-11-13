<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Amp\File\exists;

#[AsCommand(
    name: 'init',
    description: 'Generate Sample Mager Definition',
)]
final class InitCommand extends Command
{
    public function __construct(
    ) {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        runLocally(fn() => $this->generateSampleDefinition(), showProgress: false);

        $io->success('Project successfully initialized.');

        return Command::SUCCESS;
    }

    private function generateSampleDefinition(): \Generator
    {
        if (exists('mager.yaml')) {
            return;
        }

        $definition = <<<MAGER
name: amazing-app
services:
  http:
    proxy:
      ports:
        - 80/tcp
      rule: Host(`{\$host}`)
      host: amazing-app.com
    execute_once:
      - echo 'true'
    after_deploy:
      - echo 'true'
    before_deploy:
      - echo 'true'
MAGER;

        yield "echo '$definition' > mager.yaml";
    }
}
