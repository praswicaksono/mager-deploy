<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Server\Docker\DockerService;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\ExecutorInterface;
use App\Component\Server\Result;
use App\Component\Server\Task\DockerServiceList;
use App\Helper\Server;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use function Amp\async;

#[AsCommand(
    name: 'mager:service',
    description: 'Show list of running services',
)]
final class MagerServiceCommand extends Command
{
    public function __construct(
        private readonly Config $config
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $namespace = 'local';
        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor);

        /** @var Result<ArrayCollection<DockerService>> $result */
        $result = async(function () use ($server, $namespace) {
            return $server->exec(DockerServiceList::class);
        })->await();
        $result = $result->data;

        $table = $io->createTable();
        $table->setHeaders(['ID', 'Namespace', 'App', 'Image', 'Ports']);;

        foreach ($result as $service) {
            $table->addRow(
                [
                    $service->id,
                    $namespace,
                    str_replace("{$namespace}-", '', $service->name),
                    $service->image,
                    $service->ports
                ]
            );
        }

        $table->render();

        return Command::SUCCESS;
    }
}
