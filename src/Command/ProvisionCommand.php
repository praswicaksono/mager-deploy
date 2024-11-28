<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Data\Server;
use App\Helper\CommandHelper;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'provision',
    description: 'Provision server',
)]
final class ProvisionCommand extends Command
{
    private SymfonyStyle $io;

    /**
     * @var array<string, callable(): void>
     */
    private array $supportedOs;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
        $this->supportedOs = [
            'ubuntu' => require dirname(__DIR__).'/../provisions/ubuntu.php',
        ];
    }

    protected function configure(): void
    {
        $this->addArgument('namespace', InputArgument::REQUIRED);
        $this->addArgument('hosts', InputArgument::IS_ARRAY);

        $this->addOption('file', 'f', InputOption::VALUE_REQUIRED);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $hosts = $input->getArgument('hosts') ?? [];
        $file = $input->getOption('file') ?? null;

        $this->io->title('Provisioning');
        $this->provision($namespace, $hosts, $file);

        return Command::SUCCESS;
    }

    /**
     * @param string[] $hosts
     */
    private function provision(string $namespace, array $hosts = [], ?string $file = null): void
    {
        Assert::notEmpty($this->config->get($namespace), "Namespace {$namespace} are not initialized, run mager namespace:add {$namespace}");

        $groupByOs = [];

        $servers = $this->config->getServers($namespace);
        if (!empty($hosts)) {
            $servers = $servers->filter(static fn (Server $server) => in_array($server->hostname, $hosts));
        }

        foreach ($servers as $server) {
            $os = trim(runOnServer(static fn () => yield from CommandHelper::getOSName(), $server));
            if (!array_key_exists($os, $this->supportedOs)) {
                $this->io->error("[{$server->hostname} - {$server->ip}] {$os} currently not supported");

                return;
            }
            $groupByOs[$os][] = $server;
        }

        foreach ($groupByOs as $os => $servers) {
            $script = $this->supportedOs[$os];
            if (null !== $file && file_exists($file)) {
                $script = require $file;
            }
            runOnServerCollection($script, new ArrayCollection($servers));
        }
    }
}
