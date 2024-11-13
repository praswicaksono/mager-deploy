<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Amp\File\exists;

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
            0
        );

        if ($template === 'default') {
            runLocally(fn() => $this->generateSampleDefinition(), showProgress: false);
        } else {
            runLocally(fn() => $this->downloadAndExtract($template), showProgress: false);
        }

        $this->io->success('Project successfully initialized.');

        return Command::SUCCESS;
    }

    private function downloadAndExtract(string $template): \Generator
    {
        $template = "template-{$template}";
        $folder = "{$template}-master";

        $url = "https://github.com/magerdeploy/{$template}/archive/refs/heads/master.zip";

        yield "curl -L --progress-bar {$url} -o 'mager-template.zip'";
        yield 'unzip mager-template.zip -d . && rm -rf mager-template.zip';
        yield "rm -f {$folder}/LICENSE.md && rm -f {$folder}/README.md";
        yield "chmod 755 {$folder} && find {$folder} -type d -exec chmod 755 {} \; && find {$folder} -type f -exec chmod 644 {} \; && mv {$folder}/* . && rm -rf {$folder}";

        if (exists("./scripts.php")) {
            $script = require "./scripts.php";
            $reflection = new \ReflectionFunction($script);

            if ($reflection->getReturnType()->getName() === \Generator::class) {
                if ($reflection->getNumberOfParameters() === 1){
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
