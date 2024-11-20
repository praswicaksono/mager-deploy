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

    private bool $tty = false;

    private ?Server $server = null;

    private int $concurrency = 5;

    public static function create(): self
    {
        return new self();
    }

    public function withTty(bool $tty = true): self
    {
        $self = clone $this;
        $self->tty = $tty;

        return $self;
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

    public function withServer(Server $server): self
    {
        $self = clone $this;
        $self->server = $server;

        return $self;
    }

    public function withConcurrency(int $concurrency): self
    {
        $self = clone $this;
        $self->concurrency = $concurrency;

        return $self;
    }

    public function build(string $namespace, bool $local = false): RunnerInterface
    {
        if (null !== $this->server) {
            return new SingleRemoteRunner($this->io, $this->tty, $this->server);
        }

        if ($local) {
            return new LocalRunner($this->io, $this->tty);
        }

        if ($this->config->isLocal($namespace)) {
            return new LocalRunner($this->io, $this->tty);
        }

        if ($this->workerOnly) {
            $servers = $this->config->getServers($namespace)
                ->filter(fn(Server $server): bool => 'worker' === $server->role);

            return new SwooleMultiRemoteRunner($this->io, $servers, $this->concurrency);
        }

        $managerServers = $this->config->getServers($namespace)
            ->filter(fn(Server $server): bool => 'manager' === $server->role);

        // execute in single manager server
        if ($this->singleManagerServer && $this->managerOnly) {
            return new SingleRemoteRunner($this->io, $this->tty, $managerServers->first());
        }

        if (!$this->singleManagerServer && $this->managerOnly) {
            return new SwooleMultiRemoteRunner($this->io, $managerServers, $this->concurrency);
        }

        return new SwooleMultiRemoteRunner($this->io, $this->config->getServers($namespace), $this->concurrency);
    }
}
