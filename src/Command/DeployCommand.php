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
            InputArgument::REQUIRED,
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
            Assert::eq($namespace, 'local', 'Deploy preview require non-local namespace');
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
        if (!runOnManager(static fn () => yield from CommandHelper::ensureServerArePrepared($namespace), $namespace)) {
            $this->io->error("Please run 'mager prepare {$namespace}' first");

            return Command::FAILURE;
        }

        $this->io->title('Building Image');

        $version = getenv('VERSION');
        $version = false === $version ? 'latest' : $version;

        if ($this->isPreview && 'latest' !== $version) {
            $version = runLocally(static function () {
                // try to look github sha commit first
                $version = getenv('GITHUB_SHA');
                $version = false !== $version ? $version : trim(yield 'git rev-parse HEAD');

                return empty($version) ? 'latest' : $version;
            }, throwError: false);
        }

        if ($this->isDev) {
            $version = 'dev-'.$version;
        }

        if ('latest' === $version) {
            $this->io->warning('VERSION environment variable is not detected, using latest as image tag');
        }

        $imageName = $definition->build->image;

        if (file_exists($definition->build->dockerfile)) {
            $imageName = "{$imageName}:{$version}";
            $push = true;
            if ($this->config->isSingleNode($namespace) || $this->config->isLocal($namespace)) {
                $push = false;
            }

            try {
                runLocally(function () use ($namespace, $imageName, $definition, $push) {
                    yield from CommandHelper::buildImage($imageName, $definition->build, $push);
                    if (false === $push && !$this->config->isLocal($namespace)) {
                        yield from CommandHelper::dumpAndCompressImage($imageName, $namespace, $definition->name);
                    }
                });
            } catch (\Exception $e) {
                $this->io->error($e->getMessage());

                return Command::FAILURE;
            }

            if ($this->config->isSingleNode($namespace) && !$this->config->isLocal($namespace)) {
                $this->io->title('Transfer and Load Image');
                runOnManager(static fn () => CommandHelper::transferAndLoadImage($namespace, $definition->name), $namespace);
            }
        }

        $this->io->title('Deploying Service');

        /**
         * @var Service $service
         */
        foreach ($definition->services as $service) {
            if (!empty($service->beforeDeploy)) {
                $this->io->section("Executing Before Deploy Hooks {$service->name}");
                foreach ($service->beforeDeploy as $job) {
                    runOnManager(fn () => $this->runJob(
                        job: $job,
                        namespace: $namespace,
                        imageName: $imageName,
                        serviceName: "{$definition->name}-{$service->name}",
                        service: $service,
                    ), $namespace);
                }
            }

            if (null !== $service->proxy->rule && $this->config->isLocal($namespace)) {
                runLocally(fn () => $this->setupTls($namespace, $service));
            }

            $this->io->section("Deploying {$service->name}");
            runOnManager(fn () => yield from $this->deploy(
                namespace: $namespace,
                imageName: $imageName,
                serviceName: "{$definition->name}-{$service->name}",
                service: $service,
                isLocal: $this->config->isLocal($namespace),
            ), $namespace);

            if (!empty($service->afterDeploy)) {
                $this->io->section("Executing After Deploy Hooks {$service->name}");
                foreach ($service->afterDeploy as $job) {
                    runOnManager(fn () => $this->runJob(
                        job: $job,
                        namespace: $namespace,
                        imageName: $imageName,
                        serviceName: "{$definition->name}-{$service->name}",
                        service: $service,
                    ), $namespace);
                }
            }
        }

        $this->io->success('Your application has been deployed.');

        return Command::SUCCESS;
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

        yield "Executing {$job}" => DockerCreateService::create($namespace, $name, $imageName)
            ->withMode('replicated-job')
            ->withRegistryAuth()
            ->withConstraints($constraint)
            ->withNetworks($network)
            ->withCommand($job)
            ->withRestartCondition('none')
            ->withEnvs($service->env)
        ;

        yield from CommandHelper::removeService($namespace, $name, 'replicated-job');
    }

    private function deploy(
        string $namespace,
        string $imageName,
        string $serviceName,
        Service $service,
        bool $isLocal,
    ): \Generator {
        $fullServiceName = "{$namespace}-{$serviceName}";

        // just update image if service exists
        // TODO: update other configuration like cpu, labels, mount, ram
        $isRunning = yield from CommandHelper::isServiceRunning($namespace, $serviceName);
        if ($isRunning) {
            yield "Updating {$fullServiceName}" => "docker service update --image {$imageName} --force {$namespace}-{$serviceName}";

            return;
        }

        if (!empty($service->executeOnce)) {
            $this->io->section('Executing one time deployment hook');
            foreach ($service->executeOnce as $job) {
                yield from $this->runJob(
                    job: $job,
                    namespace: $namespace,
                    imageName: $imageName,
                    serviceName: $service->name,
                    service: $service,
                );
            }
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

        yield "Deploying {$fullServiceName}" => DockerCreateService::create($namespace, $serviceName, $imageName)
            ->withRegistryAuth()
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
            ->withLimitMemory($service->option->limitMemory)
        ;
    }

    private function setupTls(string $namespace, Service $service): \Generator
    {
        $this->io->section("Generate TLS Certificate for {$service->proxy->host}");

        yield from CommandHelper::generateTlsCertificateLocally($namespace, $service->proxy->host);
        ConfigHelper::registerTLSCertificateLocally($service->proxy->host);

        yield from CommandHelper::removeService($namespace, 'generate-tls-cert', 'replicated-job');
    }
}
