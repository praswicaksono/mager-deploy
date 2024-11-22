<?php

declare(strict_types=1);

namespace App\Command;

use App\Component\Config\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Webmozart\Assert\Assert;

#[AsCommand(
    name: 'service:del',
    description: 'Delete and stop running service',
)]
final class ServiceDelCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly Config $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('namespace', InputArgument::REQUIRED, 'Namespace name')
            ->addArgument('name', InputArgument::REQUIRED, 'Service name')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $namespace = $input->getArgument('namespace');
        $name = $input->getArgument('name');

        $config = $this->config->get($namespace);

        Assert::notEmpty($config, "Namespace {$namespace} are not initialized, run mager namespace:add {$namespace}");

        return runOnManager(fn () => $this->deleteService($namespace, $name), $namespace, showProgress: false);
    }

    private function deleteService(string $namespace, string $name): \Generator
    {
        $apps = $this->config->get("{$namespace}.apps") ?? [];

        $fullServiceName = "{$namespace}-{$name}";

        $id = yield sprintf('docker service ls --format "{{.ID}}|{{.Name}}" --filter name=%s', $fullServiceName);

        if (empty($id)) {
            $this->io->error("Service {$fullServiceName} is not running");

            return Command::FAILURE;
        }

        foreach (explode(PHP_EOL, $id) as $services) {
            if (empty($services)) {
                continue;
            }

            [$serviceId, $serviceName] = explode('|', $services);

            if ($serviceName === $fullServiceName) {
                yield sprintf('docker service rm %s', $serviceId);
                $this->io->success('Service has been deleted.');

                if (array_key_exists($name, $apps)) {
                    $appDir = getenv('HOME')."/.mager/apps/{$namespace}/{$name}";

                    yield "rm -rf {$appDir}";
                    unset($apps[$name]);
                    $this->config->set("{$namespace}.apps", $apps);
                    $this->config->save();
                }

                return Command::SUCCESS;
            }
        }
    }
}
