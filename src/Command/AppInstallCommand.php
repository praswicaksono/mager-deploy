<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\AppDefinition;
use App\Component\Config\Config;
use App\Component\Config\Definition\ProxyPort;
use App\Component\Config\YamlAppServiceDefinitionBuilder;
use App\Component\TaskRunner\RunnerBuilder;
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

use function Amp\File\exists;

#[AsCommand(
    name: 'app:install',
    description: 'Install 3rd party apps',
)]
final class AppInstallCommand extends Command
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
            'Deploy service to servers that listed for given namespace',
        );

        $this->addArgument(
            'url',
            InputArgument::REQUIRED,
            'URL package for 3rd party apps',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $namespace = $input->getArgument('namespace');
        $url = $input->getArgument('url');

        // TODO: verify namespace

        $r = RunnerBuilder::create()
            ->withIO($this->io)
            ->withConfig($this->config)
            ->build($namespace);

        $cwd = $r->run($this->resolvePackage($namespace, $url));

        if (! exists($cwd . '/mager.yaml')) {
            throw new \InvalidArgumentException("Cant find 'mager.yaml' in '$cwd'");
        }

        $appDefinitionBuilder = new YamlAppServiceDefinitionBuilder();
        /** @var AppDefinition $appDefinition */
        $appDefinition = $appDefinitionBuilder->build($cwd . '/mager.yaml');

        if (($code = $r->run($this->deploy($namespace, $cwd, $appDefinition))) === Command::FAILURE) {
            return $code;
        }

        $this->config->set("{$namespace}.apps.{$url}", true);
        $this->config->save();

        return $code;
    }

    private function deploy(string $namespace, string $cwd, AppDefinition $appDefinition): \Generator
    {
        if (!empty(yield CommandHelper::isServiceRunning($namespace, "app-{$appDefinition->name}"))) {
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
        if ($appDefinition->proxy !== null) {
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
            yield from CommandHelper::transferAndLoadImage($namespace, $image, $this->config->isLocal($namespace));
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

        yield DockerCreateService::create($namespace, "app-{$appDefinition->name}", $image)
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

        $cwd = $dir . '/' . $appName;

        return [
            $dir, $appName, $cwd,
        ];
    }

    private function downloadFromGithub(string $namespace, string $url): \Generator
    {
        // Resolve tag, use master if not defined
        [$githubUrl, $tag] = explode('@', $url);
        $file = '/archive/refs/heads/master.zip';
        if (!empty($tag)) {
            $file = "/archive/refs/tags/{$tag}.zip";
        }
        $githubUrl .= $file;

        [$dir, $appName, $cwd] = $this->prepareWorkingDirectory($namespace, $url);

        yield "mkdir -p -m755 {$dir}";
        yield "curl -L --progress-bar {$githubUrl} -o '{$dir}/{$appName}.zip'";
        yield "unzip {$dir}/{$appName}.zip -d {$dir}";
        yield "mv {$dir}/{$appName}-master {$dir}/{$appName} && chmod -R 755 {$dir}/{$appName}";
        yield "rm -f {$dir}/{$appName}.zip";

        return $cwd;
    }

    private function symlinkFromLocalFolder(string $namespace, string $url): \Generator
    {
        [$dir, , $cwd] = $this->prepareWorkingDirectory($namespace, $url);

        yield "mkdir -p -m755 {$dir}";
        yield "ln -nsf {$url} {$dir}";

        return $cwd;
    }
}
