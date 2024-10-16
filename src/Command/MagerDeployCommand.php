<?php

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Server\ExecutorFactory;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\Param;
use App\Helper\Server;
use App\Helper\Traefik;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'mager:deploy',
    description: 'Add a short description for your command',
)]
final class MagerDeployCommand extends Command
{
    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'domain',
            null,
            InputOption::VALUE_REQUIRED,
            'Domain name of app e.g app-name.com',
        );

        $this->addOption(
            'port',
            null,
            InputOption::VALUE_REQUIRED,
            'Port that exposed by container',
        );

        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            'Target namespace',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $domain = $input->getOption('domain') ?? null;
        $port = $input->getOption('port') ?? null;
        $namespace = $input->getOption('namespace') ?? 'local';

        Assert::notEmpty($domain, '--domain must not be empty');
        Assert::notEmpty($port, '--port must not be empty');

        $port = intval($port);
        $executor = (new ExecutorFactory($this->config))($namespace);
        $server = Server::withExecutor($executor);

        $constraint = ['node.role==worker'];
        if ($this->config->isSingleNode($namespace)) {
            $constraint = ['node.role==manager'];
        }

        $build = new ArrayInput([
            'command' => 'mager:build',
        ]);

        $this->getApplication()->doRun($build, $output);

        $cwd = getcwd();
        $cwdArr = explode('/', $cwd);

        $name = end($cwdArr);

        $imageName = "mgr.la/{$namespace}-{$name}";

        $io->info('Deploying service ...');

        $server->exec(
            DockerServiceCreate::class,
            [
                Param::GLOBAL_NAMESPACE->value => $namespace,
                Param::DOCKER_SERVICE_IMAGE->value => $imageName,
                Param::DOCKER_SERVICE_NAME->value => "{$namespace}-{$name}",
                Param::DOCKER_SERVICE_REPLICAS->value => 1,
                Param::DOCKER_SERVICE_CONSTRAINTS->value => $constraint,
                Param::DOCKER_SERVICE_NETWORK->value => [
                    "{$namespace}-main",
                    Config::MAGER_GLOBAL_NETWORK,
                ],
                Param::DOCKER_SERVICE_LABEL->value => Traefik::enable(
                    "{$namespace}-{$name}",
                    $domain,
                    $port,
                ),
            ],
        );

        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');

        return Command::SUCCESS;
    }
}
