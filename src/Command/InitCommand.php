<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'init',
    description: 'Generate Sample Mager Definition',
)]
final class InitCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
    ) {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $template = $this->io->choice(
            'Select Mager Template',
            [
                'default',
                'php-symfony',
            ],
            0,
        );

        if ('default' === $template) {
            runLocally(fn () => $this->generateSampleDefinition(), showProgress: false);
        } else {
            runLocally(fn () => $this->downloadAndExtract($template), showProgress: false);
        }

        $this->io->success('Project successfully initialized.');

        return Command::SUCCESS;
    }

    private function downloadAndExtract(string $template): \Generator
    {
        $template = "template-{$template}";
        $folder = "{$template}-master";

        $url = "https://github.com/magerdeploy/{$template}/archive/refs/heads/master.zip";

        yield <<<CMD
            curl -L --progress-bar {$url} -o 'mager-template.zip'
            unzip mager-template.zip -d . && rm -rf mager-template.zip
            rm -f {$folder}/LICENSE.md && rm -f {$folder}/README.md
            chmod 755 {$folder} && find {$folder} -type d -exec chmod 755 {} \\; && find {$folder} -type f -exec chmod 644 {} \\; && mv {$folder}/* . && rm -rf {$folder}
        CMD;

        if (file_exists('./scripts.php')) {
            $script = require './scripts.php';
            $reflection = new \ReflectionFunction($script);

            // @phpstan-ignore-next-line
            if (\Generator::class === $reflection->getReturnType()->getName()) {
                if (1 === $reflection->getNumberOfParameters()) {
                    yield from $script($this->io);
                } else {
                    yield from $script();
                }
            } else {
                $this->io->warning('Template scripts must return a generator instance. Skipping...');
            }

            yield 'rm -rf scripts.php';
        }
    }

    private function generateSampleDefinition(): \Generator
    {
        if (file_exists('mager.yaml')) {
            return;
        }

        $definition = <<<'MAGER'
name: amazing-app
services:
  http:
    proxy:
      ports:
        - 80/tcp
      rule: Host(`{$host}`)
      host: amazing-app.com
    execute_once:
      - echo 'true'
    after_deploy:
      - echo 'true'
    before_deploy:
      - echo 'true'
MAGER;

        yield "echo '{$definition}' > mager.yaml";
    }
}
