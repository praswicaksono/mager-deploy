<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Helper\Encryption;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'init',
    description: 'Initialize mager configuration',
)]
final class MagerInitCommand extends Command
{
    private SymfonyStyle $io;

    private InputInterface $input;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'local',
            null,
            InputOption::VALUE_NONE,
            'Setup for mager for local development include proxy auto configuration',
        );

        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_OPTIONAL,
            'Create namespace for the project',
        );
    }

    private function initializeConfig(): void
    {
        $isLocal = $this->input->getOption('local') ?? false;
        $namespace = $this->input->getOption('namespace') ?? null;
        $globalNetwork = Config::MAGER_GLOBAL_NETWORK;

        if (!$isLocal) {
            Assert::notEmpty($namespace, '--namespace must be a non-empty string');
        } else {
            $namespace = 'local';
        }

        // set default value for local server
        $proxyDashboard = 'dashboard.traefik.wip';
        $proxyUser = 'admin';
        $proxyPassword = 'admin123';
        $server['ip'] = '127.0.0.1';
        $server['ssh_user'] = null;
        $server['ssh_port'] = null;
        $server['ssh_key_path'] = null;
        $server['role'] = 'manager';

        if (!$isLocal) {
            $server['ip'] = $this->io->askQuestion(new Question('Please enter your server ip: ', '127.0.0.1'));
            $server['ssh_user'] = $this->io->askQuestion(new Question('Please enter manager ssh user:', 'root'));
            $server['ssh_port'] = (int) $this->io->askQuestion(new Question('Please enter manager ssh port:', 22));
            $server['ssh_key_path'] = $this->io->askQuestion(new Question('Please enter manager ssh key path:', '~/.ssh/id_rsa'));
            $proxyDashboard = $this->io->askQuestion(new Question('Please enter proxy dashboard url:', 'dashboard.traefik.wip'));
            $proxyUser = $this->io->askQuestion(new Question('Please enter proxy user:', 'admin'));
            $proxyPassword = $this->io->askQuestion(new Question('Please enter proxy password:', 'admin123'));
        }
        $servers = [];
        $servers[] = $server;

        $this->config->set("{$namespace}.servers", $servers);
        $this->config->set("{$namespace}.namespace", $namespace);
        $this->config->set("{$namespace}.proxy_dashboard", $proxyDashboard);
        $this->config->set("{$namespace}.proxy_user", $proxyUser);
        $this->config->set("{$namespace}.proxy_password", Encryption::Htpasswd($proxyPassword));
        $this->config->set("{$namespace}.is_local", $isLocal);
        $this->config->set("{$namespace}.network", "{$namespace}-main");
        $this->config->set("{$namespace}.is_single_node", true);
        $this->config->set('global_network', $globalNetwork);

        $this->config->save();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->input = $input;
        $this->io = new SymfonyStyle($input, $output);

        $this->initializeConfig();

        $this->io->success('Config successfully initialized');

        return Command::SUCCESS;
    }
}
