<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Server\Docker\DockerService;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Result;
use App\Component\Server\Task\DockerServiceList;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use Doctrine\Common\Collections\ArrayCollection;
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
        $io = new SymfonyStyle($input, $output);

        $namespace = $input->getArgument('namespace');
        $config = $this->config->get($namespace);

        Assert::notEmpty($namespace, '--namespace must be a non-empty string');
        Assert::notEmpty($config, "Namespace {$namespace} are not initialized, run mager mager:init --namespace {$namespace}");

        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor, $io);

        /** @var Result<ArrayCollection<int, DockerService>> $result */
        $result = $server->exec(
            DockerServiceList::class,
            [
                Param::DOCKER_SERVICE_LIST_FILTER->value => [
                    "name={$namespace}-",
                ],
            ],
            continueOnError: true,
            showOutput: false,
        );
        /** @var ArrayCollection<int, DockerService> $collection */
        $collection = $result->data;

        $table = $io->createTable();
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

        return Command::SUCCESS;
    }
}
