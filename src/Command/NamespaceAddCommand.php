<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\TaskRunner\Util;
use App\Entity\DockerNode;
use App\Helper\Encryption;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'namespace:add',
    description: 'Add new namespace',
)]
final class NamespaceAddCommand extends Command
{
    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::REQUIRED, 'Namespace name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $isLocal = 'local' === $namespace;

        $config = $this->config->get($namespace);
        Assert::isEmpty($config, "Namespace {$namespace} already exists");

        $server = [];

        $server['ip'] = '127.0.0.1';
        $server['ssh_user'] = null;
        $server['ssh_port'] = null;
        $server['ssh_key_path'] = null;
        $server['role'] = 'manager';
        $server['hostname'] = 'default';
        $proxyDashboard = 'dashboard.traefik.wip';
        $proxyUser = 'admin';
        $proxyPassword = 'admin123';

        if (!$isLocal) {
            $server['role'] = 'manager';
            $server['ip'] = $io->askQuestion(new Question('Please enter your server ip: ', '127.0.0.1'));
            $server['ssh_user'] = $io->askQuestion(new Question('Please enter manager ssh user:', 'root'));
            $server['ssh_port'] = (int) $io->askQuestion(new Question('Please enter manager ssh port:', 22));
            $server['ssh_key_path'] = $io->askQuestion(new Question('Please enter manager ssh key path:', '~/.ssh/id_rsa'));
            $proxyDashboard = $io->askQuestion(new Question('Please enter proxy dashboard url:', 'dashboard.traefik.wip'));
            $proxyUser = $io->askQuestion(new Question('Please enter proxy user:', 'admin'));
            $proxyPassword = $io->askQuestion(new Question('Please enter proxy password:', 'admin123'));
        }

        $this->config->set("{$namespace}.servers.default", $server);
        $this->config->set("{$namespace}.proxy_dashboard", $proxyDashboard);
        $this->config->set("{$namespace}.proxy_user", $proxyUser);
        $this->config->set("{$namespace}.proxy_password", Encryption::Htpasswd($proxyPassword));

        $this->config->save();

        try {
            $hostname = trim(runOnManager(static fn () => yield 'hostname', $namespace));
        } catch (\Exception $e) {
            $this->config->delete($namespace);
            $this->config->save();

            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $sanitizedHostname = str_replace('.', '_', $hostname);
        $server['hostname'] = $sanitizedHostname;

        /** @var Collection<int, DockerNode> $nodeCollection */
        $nodeCollection = Util::deserializeJsonList(
            runOnManager(static fn () => yield 'docker node ls --format json', $namespace),
            static fn (string $item): DockerNode => DockerNode::fromJsonString($item)
        );

        foreach ($nodeCollection as $node) {
            if ($node->hostname === $hostname) {
                $server['id'] = $node->id;
            }
        }

        $this->config->set("{$namespace}.servers.{$hostname}", $server);
        $this->config->delete("{$namespace}.servers.default");
        $this->config->save();

        if ($isLocal) {
            touch(getenv('HOME').'/.mager/dynamic.yaml');
        }

        $io->success('Namespace successfully added!');

        return Command::SUCCESS;
    }
}
