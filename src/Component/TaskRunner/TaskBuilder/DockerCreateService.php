<?php

declare(strict_types=1);

namespace App\Component\TaskRunner\TaskBuilder;

use App\Component\TaskRunner\TaskInterface;
use App\Component\TaskRunner\Util;

final class DockerCreateService implements TaskInterface
{
    /** @var string[] */
    private array $envs = [];

    /** @var string[] */
    private array $mounts = [];

    /** @var string[] */
    private array $labels = [];

    /** @var string[] */
    private array $constraints = [];

    /** @var string[] */
    private array $portPublish = [];

    /** @var string[] */
    private array $networks = [];

    /** @var string[] */
    private array $configs = [];

    /** @var string[] */
    private array $hosts = [];

    private ?string $command = null;

    private ?string $mode = null;

    private ?string $updateFailureAction = null;

    private ?string $updateOrder = null;

    private ?string $workdir = null;

    private ?float $limitCpu = null;

    private ?string $limitMemory = null;

    private ?string $restartCondition = null;

    private ?int $restartMaxAttempts = null;

    private ?string $stopSignal = null;

    private ?string $user = null;

    private int $replicas = 1;

    private bool $withRegistryAuth = false;

    private string $namespace;

    private string $name;

    private string $image;

    public static function create(string $namespace, string $name, string $image): self
    {
        $self = new self();
        $self->namespace = $namespace;
        $self->name = $name;
        $self->image = $image;

        return $self;
    }

    /**
     * @param string[] $hosts
     */
    public function withHosts(array $hosts): self
    {
        $self = clone $this;
        $self->hosts = $hosts;

        return $self;
    }

    /**
     * @param string[] $envs
     */
    public function withEnvs(array $envs): self
    {
        $self = clone $this;
        $self->envs = $envs;

        return $self;
    }

    /**
     * @param string[] $mounts
     */
    public function withMounts(array $mounts): self
    {
        $self = clone $this;
        $self->mounts = $mounts;

        return $self;
    }

    /**
     * @param string[] $labels
     */
    public function withLabels(array $labels): self
    {
        $self = clone $this;
        $self->labels = $labels;

        return $self;
    }

    /**
     * @param string[] $constraints
     */
    public function withConstraints(array $constraints): self
    {
        $self = clone $this;
        $self->constraints = $constraints;

        return $self;
    }

    /**
     * @param string[] $portPublish
     */
    public function withPortPublish(array $portPublish): self
    {
        $self = clone $this;
        $self->portPublish = $portPublish;

        return $self;
    }

    /**
     * @param string[] $networks
     */
    public function withNetworks(array $networks): self
    {
        $self = clone $this;
        $self->networks = $networks;

        return $self;
    }

    public function withCommand(?string $command = null): self
    {
        $self = clone $this;
        $self->command = $command;

        return $self;
    }

    public function withReplicas(int $replicas): self
    {
        $self = clone $this;
        $self->replicas = $replicas;

        return $self;
    }

    public function withUpdateFailureAction(string $updateFailureAction): self
    {
        $self = clone $this;
        $self->updateFailureAction = $updateFailureAction;

        return $self;
    }

    public function withUpdateOrder(string $updateOrder): self
    {
        $self = clone $this;
        $self->updateOrder = $updateOrder;

        return $self;
    }

    public function withWorkdir(string $workdir): self
    {
        $self = clone $this;
        $self->workdir = $workdir;

        return $self;
    }

    public function withLimitCpu(?float $limitCpu): self
    {
        $self = clone $this;
        $self->limitCpu = $limitCpu;

        return $self;
    }

    public function withLimitMemory(?string $limitMemory): self
    {
        $self = clone $this;
        $self->limitMemory = $limitMemory;

        return $self;
    }

    public function withMode(string $mode): self
    {
        $self = clone $this;
        $self->mode = $mode;

        return $self;
    }

    public function withUser(string $user): self
    {
        $self = clone $this;
        $self->user = $user;

        return $self;
    }

    /**
     * @param string[] $configs
     */
    public function withConfigs(array $configs): self
    {
        $self = clone $this;
        $self->configs = $configs;

        return $self;
    }

    public function withRestartCondition(string $restartCondition): self
    {
        $self = clone $this;
        $self->restartCondition = $restartCondition;

        return $self;
    }

    public function withRestartMaxAttempts(int $restartMaxAttempts): self
    {
        $self = clone $this;
        $self->restartMaxAttempts = $restartMaxAttempts;

        return $self;
    }

    public function withStopSignal(?string $stopSignal = null): self
    {
        $self = clone $this;
        $self->stopSignal = $stopSignal;

        return $self;
    }

    public function withRegistryAuth(bool $enable = true): self
    {
        $self = clone $this;
        $self->withRegistryAuth = $enable;

        return $self;
    }

    public function cmd(): array
    {
        $cmd = ['docker', 'service', 'create'];

        $cmd = Util::buildOptions('--constraint', $this->constraints, $cmd);
        $cmd = Util::buildOptions('--network', $this->networks, $cmd);
        $cmd = Util::buildOptions('--env', $this->envs, $cmd);
        $cmd = Util::buildOptions('--publish', $this->portPublish, $cmd);
        $cmd = Util::buildOptions('--label', $this->labels, $cmd);
        $cmd = Util::buildOptions('--mount', $this->mounts, $cmd);
        $cmd = Util::buildOptions('--config', $this->configs, $cmd);
        $cmd = Util::buildOptions('--host', $this->hosts, $cmd);

        if (null !== $this->updateFailureAction) {
            $cmd[] = "--update-failure-action {$this->updateFailureAction}";
        }

        if (null !== $this->updateOrder) {
            $cmd[] = "--update-order {$this->updateOrder}";
        }

        if (null !== $this->mode) {
            $cmd[] = "--mode {$this->mode}";
        }

        if (null !== $this->limitCpu) {
            $cmd[] = "--limit-cpu {$this->limitCpu}";
        }

        if (null !== $this->limitMemory) {
            $cmd[] = "--limit-memory {$this->limitMemory}";
        }

        if (null !== $this->workdir) {
            $cmd[] = "--workdir {$this->workdir}";
        }

        if (null !== $this->restartCondition) {
            $cmd[] = "--restart-condition {$this->restartCondition}";
        }

        if (null !== $this->restartMaxAttempts) {
            $cmd[] = "--restart-max-attempts {$this->restartMaxAttempts}";
        }

        if (null !== $this->stopSignal) {
            $cmd[] = "--stop-signal {$this->stopSignal}";
        }

        if (null !== $this->user) {
            $cmd[] = "--user {$this->user}";
        }

        if ($this->withRegistryAuth) {
            $cmd[] = '--with-registry-auth';
        }

        $cmd[] = "--name {$this->namespace}-{$this->name}";
        $cmd[] = "--replicas {$this->replicas}";

        $cmd[] = $this->image;

        if (null !== $this->command) {
            $cmd[] = $this->command;
        }

        return $cmd;
    }
}
