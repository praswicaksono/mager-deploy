<?php

namespace App\Command;

use Amp\Http\Client\Connection\DefaultConnectionFactory;
use Amp\Http\Client\Connection\UnlimitedConnectionPool;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Socket\StaticSocketConnector;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Amp\Socket\socketConnector;

#[AsCommand(
    name: 'docker:socket',
    description: 'Add a short description for your command',
)]
class DockerSocketCommand extends Command
{
    public function __construct()
    {
        parent::__construct();
    }

    protected function configure(): void {}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connector = new StaticSocketConnector('unix:///var/run/docker.sock', socketConnector());

        $client = (new HttpClientBuilder())
            ->usingPool(new UnlimitedConnectionPool(new DefaultConnectionFactory($connector)))
            ->build();

        $day = strtotime('-1 day');
        $request = new Request("http://docker/events?since={$day}");
        $request->setTransferTimeout(-1);
        $request->setInactivityTimeout(-1);

        // Make an asynchronous HTTP request
        $response = $client->request($request);

        while (null !== $chunk = $response->getBody()->read()) {
            $io->write($chunk);
        }

        return Command::SUCCESS;
    }
}
