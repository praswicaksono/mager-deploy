<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\TaskRunner\RunnerBuilder;
use App\Component\TaskRunner\Util;
use App\Entity\DockerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'service:ls',
    description: 'List of running services',
)]
final class ServiceLsCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'namespace',
            InputArgument::OPTIONAL,
            'Namespace',
            'local',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $namespace = $input->getArgument('namespace');
        $config = $this->config->get($namespace);

        Assert::notEmpty($namespace, '--namespace must be a non-empty string');
        Assert::notEmpty($config, "Namespace {$namespace} are not initialized, run mager mager:init --namespace {$namespace}");

        $r = RunnerBuilder::create()
            ->withIO($this->io)
            ->withConfig($this->config)
            ->build($namespace);

        $r->run($this->listServices($namespace), showProgress: false, throwError: false);

        return Command::SUCCESS;
    }

    private function listServices(string $namespace): \Generator
    {
        $out = yield sprintf('docker service ls --format "{{json .}}" --filter name=%s', "{$namespace}-");

        $collection = Util::deserializeJsonList($out, function (string $item): DockerService {
            return DockerService::fromJsonString($item);
        });

        $table = $this->io->createTable();
        $table->setHeaders(['ID', 'Namespace', 'App', 'Image']);

        foreach ($collection as $service) {
            $table->addRow(
                [
                    $service->id,
                    $namespace,
                    str_replace("{$namespace}-", '', $service->name),
                    $service->image,
                ],
            );
        }

        $table->render();
    }
}
