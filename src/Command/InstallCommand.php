<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\AppDefinition;
use App\Component\Config\Config;
use App\Component\Config\Definition\ProxyPort;
use App\Component\Config\YamlAppServiceDefinitionBuilder;
use App\Component\TaskRunner\TaskBuilder\DockerCreateService;
use App\Helper\CommandHelper;
use App\Helper\HttpHelper;
use App\Helper\StringHelper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'install',
    description: 'Install third party apps',
)]
final class InstallCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'namespace',
            InputArgument::REQUIRED,
            'Namespace',
        );

        $this->addArgument(
            'url',
            InputArgument::REQUIRED,
            'URL package for third party apps',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $namespace = $input->getArgument('namespace');
        $url = $input->getArgument('url');

        $config = $this->config->get($namespace);

        Assert::notEmpty($config, "Namespace {$namespace} are not initialized, run mager namespace:add {$namespace}");

        $cwd = runLocally(fn() => $this->resolvePackage($namespace, $url));

        if (! file_exists($cwd . '/mager.yaml')) {
            throw new \InvalidArgumentException("Cant find 'mager.yaml' in '$cwd'");
        }

        $appDefinitionBuilder = new YamlAppServiceDefinitionBuilder();
        /** @var AppDefinition $appDefinition */
        $appDefinition = $appDefinitionBuilder->build($cwd . '/mager.yaml');

        if (($code = runOnManager(fn() => $this->deploy($namespace, $cwd, $appDefinition), $namespace)) === Command::FAILURE) {
            return $code;
        }

        if ($code === Command::SUCCESS) {
            $this->config->set("{$namespace}.apps.{$appDefinition->name}", $url);
            $this->config->save();
            $this->io->success("{$appDefinition->name} Successfully Installed To {$namespace}");
        }

        return $code;
    }

    private function deploy(string $namespace, string $cwd, AppDefinition $appDefinition): \Generator
    {
        if (!empty(yield CommandHelper::isServiceRunning($namespace, $appDefinition->name))) {
            $this->io->error("Service '{$appDefinition->name}' is already deployed.");

            return Command::FAILURE;
        }

        $constraint = ['node.role==worker'];
        if ($this->config->isSingleNode($namespace)) {
            $constraint = ['node.role==manager'];
        }

        $network = [
            "{$namespace}-main",
        ];

        // Setup proxy if defined
        $labels = [];
        if (null !== $appDefinition->proxy->rule) {
            $labels[] = 'traefik.docker.lbswarm=true';
            $labels[] = 'traefik.enable=true';

            $rule = "Host(`{$appDefinition->proxy->host}`)";
            if (null !== $appDefinition->proxy->rule) {
                $rule = str_replace('{$host}', $appDefinition->proxy->host, $appDefinition->proxy->rule);
            }

            $fullServiceName = "{$namespace}-{$appDefinition->name}";
            $labels[] = HttpHelper::rule($fullServiceName, $rule);
            $labels[] = HttpHelper::tls($fullServiceName);

            /** @var ProxyPort $port */
            foreach ($appDefinition->proxy->ports as $port) {
                $labels[] = HttpHelper::port($fullServiceName, $port->getPort());
            }

            if (!$this->config->isLocal($namespace)) {
                $labels[] = HttpHelper::certResolver($fullServiceName);
            }
        }

        // Build image if defined
        $image = $appDefinition->build->resolveImageNameTagFromEnv();
        if (null === $appDefinition->build->image) {
            $build = new ArrayInput([
                'command' => 'build',
                '--namespace' => $namespace,
                '--target' => $appDefinition->build->target,
                '--file' => $cwd . '/' . $appDefinition->build->dockerfile,
                '--name' => $appDefinition->name,
                '--build' => 'latest',
            ]);

            $this->getApplication()->doRun($build, $this->io);
            $image = "{$namespace}-{$appDefinition->name}:latest";
            if (! $this->config->isLocal($namespace)) {
                yield from CommandHelper::transferAndLoadImage($namespace, $image);
            }
        }

        // Create docker config
        $configs = [];
        foreach ($appDefinition->config as $config) {
            $configName = StringHelper::extractConfigNameFromPath($namespace, $config->srcPath);
            $configFile = $cwd . '/' . $config->srcPath;

            if (!empty(yield sprintf('docker config ls --format "{{.ID}}" --filter name=%s', $configName))) {
                yield sprintf('docker config rm `docker config ls --format "{{.ID}}" --filter name=%s`', $configName);
            }

            yield "cat {$configFile} | docker config create {$configName} -";

            $configs[] = sprintf(
                'source=%s,target=%s,mode=0600',
                $configName,
                $config->destPath,
            );
        }

        yield "Deploying Service: {$appDefinition->name}" => DockerCreateService::create($namespace, $appDefinition->name, $image)
            ->withConstraints($constraint)
            ->withNetworks($network)
            ->withLabels($labels)
            ->withEnvs($appDefinition->resolveEnvValue())
            ->withMounts($appDefinition->parseToDockerVolume($namespace))
            ->withCommand($appDefinition->cmd)
            ->withUpdateOrder('start-first')
            ->withUpdateFailureAction('rollback')
            ->withConfigs($configs);

        return Command::SUCCESS;
    }

    private function resolvePackage(string $namespace, string $url): \Generator
    {
        return match (true) {
            str_starts_with($url, 'https://github.com') => yield from $this->downloadFromGithub($namespace, $url),
            str_starts_with($url, 'file://') => yield from $this->symlinkFromLocalFolder($namespace, str_replace('file://', '', $url)),
            default => throw new \InvalidArgumentException('URL are not supported yet'),
        };
    }

    /**
     * @return string[]
     */
    private function prepareWorkingDirectory(string $namespace, string $url): array
    {
        // Create local folder to store package
        $dir = getenv('HOME') . '/.mager/apps/' . $namespace;
        $appName = explode('/', $url);
        $appName = end($appName);
        $appName = str_replace('apps-', '', $appName);

        $cwd = $dir . '/' . $appName;

        return [
            $dir, $appName, $cwd,
        ];
    }

    private function downloadFromGithub(string $namespace, string $url): \Generator
    {
        // Resolve tag, use master if not defined
        @[$githubUrl, $tag] = explode('@', $url);

        if (empty($tag)) {
            $tag = 'master';
        }
        $file = '/archive/refs/heads/master.zip';
        if ('master' !== $tag) {
            $file = "/archive/refs/tags/{$tag}.zip";
        }

        $githubUrl .= $file;

        [$dir, $appName, $cwd] = $this->prepareWorkingDirectory($namespace, $url);

        $cmd = <<<CMD
            mkdir -p -m755 {$dir}
            curl -L --progress-bar {$githubUrl} -o '{$dir}/{$appName}.zip'
            unzip {$dir}/{$appName}.zip -d {$dir}
            mv {$dir}/apps-{$appName}-{$tag} {$cwd}
            chmod 755 {$cwd}
            find {$cwd} -type d -exec chmod 755 {} \; && find {$cwd} -type f -exec chmod 644 {} \;
            rm -f {$cwd}.zip
        CMD;

        yield 'Downloading App' => $cmd;

        return $cwd;
    }

    private function symlinkFromLocalFolder(string $namespace, string $url): \Generator
    {
        [$dir, , $cwd] = $this->prepareWorkingDirectory($namespace, $url);

        yield <<<CMD
        mkdir -p -m755 {$dir}
        ln -nsf {$url} {$dir}
        CMD;

        return $cwd;
    }
}
