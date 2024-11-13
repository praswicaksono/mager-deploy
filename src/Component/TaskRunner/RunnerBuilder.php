<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

use App\Component\Config\Config;
use App\Component\Config\Data\Server;
use Symfony\Component\Console\Style\SymfonyStyle;

final class RunnerBuilder
{
    private Config $config;

    private SymfonyStyle $io;

    private bool $singleManagerServer = true;

    private bool $managerOnly = true;

    private bool $workerOnly = false;

    public static function create(): self
    {
        return new self();
    }

    public function withConfig(Config $config): self
    {
        $self = clone $this;
        $self->config = $config;

        return $self;
    }

    public function withIO(SymfonyStyle $io): self
    {
        $self = clone $this;
        $self->io = $io;

        return $self;
    }

    public function onSingleManagerServer(bool $enable): self
    {
        $self = clone $this;
        $self->singleManagerServer = $enable;

        return $self;
    }

    public function onManagerOnly(bool $enable): self
    {
        $self = clone $this;
        $self->managerOnly = $enable;

        return $self;
    }

    public function onWorkerOnly(bool $enable): self
    {
        $self = clone $this;
        $self->workerOnly = $enable;

        return $self;
    }

    public function build(string $namespace, bool $local = false): RunnerInterface
    {
        if ($local) {
            return new LocalRunner($this->io);
        }

        if ($this->config->isLocal($namespace)) {
            return new LocalRunner($this->io);
        }

        if ($this->workerOnly) {
            $server = $this->config->getServers($namespace)
                ->filter(fn(Server $server): bool => 'worker' === $server->role);

            return new AmpPhpParallelRemoteRunner($this->io, $server);
        }

        $server = $this->config->getServers($namespace)
            ->filter(fn(Server $server): bool => 'manager' === $server->role);

        // execute in single manager server
        if ($this->singleManagerServer && $this->managerOnly) {
            return new SingleRemoteRunner($this->io, $server->first());
        }

        return new AmpPhpParallelRemoteRunner($this->io, $server);
    }
}
