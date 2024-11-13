<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\Config\Definition\ProxyPort;
use App\Component\Config\Definition\Service;
use App\Component\Config\DefinitionBuilder;
use App\Component\Config\ServiceDefinition;
use App\Component\TaskRunner\TaskBuilder\DockerCreateService;
use App\Helper\CommandHelper;
use App\Helper\ConfigHelper;
use App\Helper\HttpHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'deploy',
    description: 'Deploy service to target server',
)]
final class DeployCommand extends Command
{
    private SymfonyStyle $io;

    private bool $isPreview = false;

    private bool $isDev = false;

    public function __construct(
        private readonly Config $config,
        private readonly DefinitionBuilder $definitionBuilder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'namespace',
            InputArgument::OPTIONAL,
            'Deploy service to servers that listed for given namespace',
        );

        $this->addOption(
            'dev',
            null,
            InputOption::VALUE_NONE,
            'Deploy service to local namespace',
        );

        $this->addOption(
            'preview',
            null,
            InputOption::VALUE_NONE,
            'Deploy service to preview environment',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $namespace = $input->getArgument('namespace');
        $this->isDev = $input->getOption('dev') ?? false;
        $this->isPreview = $input->getOption('preview') ?? false;

        $override = 'prod';
        if ($this->isPreview) {
            Assert::eq($namespace, 'local', "Deploy preview require non-local namespace");
            $override = 'preview';
        }

        if ($this->isDev) {
            $namespace = 'local';
            $override = 'dev';
        }

        $config = $this->config->get($namespace);

        Assert::notEmpty($config, "Namespace {$namespace} are not initialized, run mager namespace:add {$namespace}");

        /** @var ServiceDefinition $definition */
        $definition = $this->definitionBuilder->build(override: $override);

        $this->io->title('Checking Requirement');
        if (! runOnManager(fn() => $this->ensureServerArePrepared($namespace), $namespace)) {
            return Command::FAILURE;
        }

        $this->io->title('Building Image');

        $version = getenv('VERSION');
        $version = false === $version ? 'latest' : $version;

        if ($this->isPreview && $version !== 'latest') {
            $version = runLocally(function() {
                // try to look github sha commit first
                $version = getenv('GITHUB_SHA');
                $version = $version !== false ? $version : yield 'git rev-parse HEAD';
                return $version ?? 'latest';
            }, throwError: false);
        }

        if ($version === 'latest') {
            $this->io->warning('VERSION environment variable is not detected, using latest as image tag');
        }

        // Build target image
        $build = new ArrayInput([
            'command' => 'build',
            '--namespace' => $namespace,
            '--target' => $definition->build->target,
            '--file' => $definition->build->dockerfile,
            '--name' => $definition->name,
            '--build' => $version,
            '--save' => null,
        ]);

        $imageName = "{$namespace}-{$definition->name}:{$version}";

        $this->getApplication()->doRun($build, $output);

        $this->io->title('Transfer and Load Image');
        runOnManager(fn() => CommandHelper::transferAndLoadImage($namespace, $definition->name, $this->config->isLocal($namespace)), $namespace);

        $this->io->title('Deploying Service');
        $isLocal = $this->config->get("{$namespace}.is_local");
        /**
         * @var Service $service
         */
        foreach ($definition->services as $service) {
            $this->io->section("Executing Before Deploy Hooks {$service->name}");
            foreach ($service->beforeDeploy as $job) {
                runOnManager(fn() => $this->runJob(
                    job: $job,
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    service: $service,
                ), $namespace);
            }

            if (null !== $service->proxy->rule && $isLocal) {
                runLocally(fn() => $this->setupTls($namespace, $service));
            }

            $this->io->section("Deploying {$service->name}");
            runOnManager(fn() => $this->deploy(
                namespace: $namespace,
                imageName: $imageName,
                serviceName: $service->name,
                service: $service,
                isLocal: $isLocal,
            ), $namespace);

            $this->io->section("Executing After Deploy Hooks {$service->name}");
            foreach ($service->afterDeploy as $job) {
                runOnManager(fn() => $this->runJob(
                    job: $job,
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    service: $service,
                ), $namespace);
            }
        }

        $this->io->success('Your application has been deployed.');

        return Command::SUCCESS;
    }

    private function ensureServerArePrepared(string $namespace): \Generator
    {
        $node = yield 'docker node ls';
        if (empty($node)) {
            $this->io->error('Docker swarm are not initialized yet');

            return false;
        }

        if (empty(yield CommandHelper::isServiceRunning($namespace, 'mager_proxy'))) {
            $this->io->error('Namespace are not prepared yet');

            return false;
        }

        return true;
    }

    private function transferAndLoadImage(string $namespace, string $imageName): \Generator
    {
        if (!$this->config->isLocal($namespace)) {
            yield "upload /tmp/{$namespace}-{$imageName}.tar.gz:/tmp/{$namespace}-{$imageName}.tar.gz";
        }

        yield "docker load < /tmp/{$namespace}-{$imageName}.tar.gz";
        yield "rm -f /tmp/{$namespace}-{$imageName}.tar.gz";
        yield 'docker image prune -a';
    }

    private function runJob(
        string $job,
        string $namespace,
        string $imageName,
        string $serviceName,
        Service $service,
    ): \Generator {
        $constraint = ['node.role==worker'];
        if ($this->config->isSingleNode($namespace)) {
            $constraint = ['node.role==manager'];
        }

        $network = [
            "{$namespace}-main",
        ];

        $rand = bin2hex(random_bytes(8));
        $name = "{$serviceName}-job-{$rand}";

        yield DockerCreateService::create($namespace, $name, $imageName)
            ->withMode('replicated-job')
            ->withConstraints($constraint)
            ->withNetworks($network)
            ->withCommand($job)
            ->withRestartCondition('none')
            ->withEnvs($service->env);

        yield CommandHelper::removeService($namespace, $name, 'replicated-job');
    }

    private function deploy(
        string $namespace,
        string $imageName,
        string $serviceName,
        Service $service,
        bool $isLocal,
    ): \Generator {
        // just update image if service exists
        // TODO: update other configuration like cpu, labels, mount, ram
        if (yield CommandHelper::isServiceRunning($namespace, $serviceName)) {
            yield "docker service update --image {$imageName} --force {$namespace}-{$serviceName}";

            return;
        }

        $constraint = ['node.role==worker'];
        if ($this->config->isSingleNode($namespace)) {
            $constraint = ['node.role==manager'];
        }

        $network = [
            "{$namespace}-main",
        ];

        $labels = [];

        $labels[] = 'traefik.docker.lbswarm=true';
        $labels[] = 'traefik.enable=true';

        $fullServiceName = "{$namespace}-{$serviceName}";
        if (null !== $service->proxy->rule) {
            $rule = str_replace('{$host}', $service->proxy->host, $service->proxy->rule);

            $labels[] = HttpHelper::rule($fullServiceName, $rule);
            $labels[] = HttpHelper::tls($fullServiceName);

            /** @var ProxyPort $port */
            foreach ($service->proxy->ports as $port) {
                $labels[] = HttpHelper::port($fullServiceName, $port->getPort());
                if (443 === $port->getPort()) {
                    $labels[] = HttpHelper::tlsLoadBalancer($fullServiceName);
                }
            }
        }

        if (!$isLocal) {
            $labels[] = HttpHelper::certResolver($fullServiceName);
        }

        yield DockerCreateService::create($namespace, $serviceName, $imageName)
            ->withConstraints($constraint)
            ->withNetworks($network)
            ->withEnvs($service->resolveEnvValue())
            ->withLabels($labels)
            ->withRestartMaxAttempts(3)
            ->withMounts($service->parseToDockerVolume($namespace))
            ->withCommand(implode(' ', $service->cmd))
            ->withUpdateOrder('start-first')
            ->withUpdateFailureAction('rollback')
            ->withStopSignal($service->stopSignal)
            ->withHosts($service->hosts)
            ->withLimitCpu($service->option->limitCpu)
            ->withLimitMemory($service->option->limitMemory);
    }

    private function setupTls(string $namespace, Service $service): \Generator
    {
        $this->io->section("Generate TLS Certificate for {$service->proxy->host}");
        yield CommandHelper::generateTlsCertificateLocally($namespace, $service->proxy->host);
        ConfigHelper::registerTLSCertificateLocally($service->proxy->host);
        yield CommandHelper::removeService($namespace, 'generate-tls-cert', 'replicated-job');
    }
}
