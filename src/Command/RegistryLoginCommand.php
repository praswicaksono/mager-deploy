<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Data\Server;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'registry:login',
    description: 'Send registry credentials to all managers',
)]
final class RegistryLoginCommand extends Command
{
    public function __construct(private Config $config)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('namespace', InputArgument::REQUIRED);
        $this->addArgument('username', InputArgument::REQUIRED);
        $this->addArgument('server', InputArgument::OPTIONAL);
        $this->addOption('no-prompt', 'n', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $username = $input->getArgument('username');
        $server = $input->getArgument('server');
        $noPrompt = $input->getOption('no-prompt') ?? false;

        Assert::notEmpty($this->config->get($namespace), "Namespace {$namespace} are not initialized, run mager namespace:add {$namespace}");

        if (!$noPrompt) {
            runLocally(fn () => yield empty($server) ? "docker login -u {$username}" : "docker login -u {$username} {$server}", tty: true);
        }

        $managers = $this->config->getServers($namespace)->filter(fn (Server $server) => 'manager' === $server->role);

        $auth = file_get_contents(getenv('HOME').'/.docker/config.json');
        runOnServerCollection(static function () use ($auth) {
            yield <<<CMD
                mkdir -p \$HOME/.docker
                echo '{$auth}'> \$HOME/.docker/config.json
            CMD;
        }, $managers);

        $io->success('Credentials successfully sent to all managers');

        return Command::SUCCESS;
    }
}
