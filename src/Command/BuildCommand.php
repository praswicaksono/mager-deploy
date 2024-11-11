<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use App\Component\TaskRunner\RunnerBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

use function Amp\File\exists;

#[AsCommand(
    name: 'build',
    description: 'Build an image for mager.yaml definition',
    hidden: true,
)]
final class BuildCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'namespace',
            null,
            InputOption::VALUE_REQUIRED,
            'Create namespace for the project',
        );

        $this->addOption(
            'name',
            null,
            InputOption::VALUE_REQUIRED,
            'Image name',
        );

        $this->addOption(
            'target',
            null,
            InputOption::VALUE_OPTIONAL,
            'Dockerfile build target',
        );

        $this->addOption(
            'file',
            null,
            InputOption::VALUE_REQUIRED,
            'Dockerfile path',
        );

        $this->addOption(
            'build',
            null,
            InputOption::VALUE_REQUIRED,
            'Version of current build',
        );

        $this->addOption(
            'save',
            null,
            InputOption::VALUE_NONE,
            'Save image to temporary folder',
        );

        $this->addOption(
            'push',
            null,
            InputOption::VALUE_NONE,
            'Push image to docker registry',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $namespace = $input->getOption('namespace') ?? 'local';
        $version = $input->getOption('build') ?? 'latest';
        $name = $input->getOption('name') ?? null;
        $target = $input->getOption('target') ?? null;
        $dockerfile = $input->getOption('file') ?? 'Dockerfile';

        Assert::notEmpty($name, '--name must be a non-empty string');
        Assert::true($this->config->isNotEmpty(), "Namespace {$namespace} are not initialized, run mager mager:init --namespace {$namespace}");

        $r = RunnerBuilder::create()
            ->withIO($this->io)
            ->withConfig($this->config)
            ->build($namespace, local: true);

        if (! exists($dockerfile)) {
            $this->io->error('Dockerfile does not exist');

            return Command::FAILURE;
        }

        $imageName = "{$namespace}-{$name}:{$version}";

        return $r->run($this->buildAndSaveImage(
            $namespace,
            $dockerfile,
            $imageName,
            $name,
            $target,
            $input->getOption('save') ?? false,
            $input->getOption('push') ?? false,
        ));
    }

    public function buildAndSaveImage(
        string $namespace,
        string $file,
        string $imageName,
        string $name,
        ?string $target = null,
        bool $save = false,
        bool $push = false,
    ): \Generator {
        $build = "docker buildx build --tag {$imageName} --file {$file} --progress plain";

        if (null !== $target) {
            $build .= " --target {$target}";
        }

        if ($push) {
            $build .= ' --output registry';
        }

        yield $build . ' .';

        if ($save) {
            yield "docker save {$imageName} | gzip > /tmp/{$namespace}-{$name}.tar.gz";
        }


        $this->io->success('Your image successfully built');

        return Command::SUCCESS;
    }
}
