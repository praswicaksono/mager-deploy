<?php

declare(strict_types=1);

namespace App\Component\TaskRunner\TaskBuilder;

use App\Component\TaskRunner\TaskInterface;
use App\Component\TaskRunner\Util;

final class DockerImageBuild implements TaskInterface
{
    private string $tag;

    private string $path = '.';

    private string $file = 'Dockerfile';

    private string $progress = 'plain';

    private ?string $network = null;

    private ?string $platform = null;

    private ?string $output = null;

    private ?string $target = null;

    /**
     * @var array<string, string|int|float>
     */
    private array $args = [];

    public static function create(string $tag, string $path): self
    {
        $self = new self();
        $self->tag = $tag;
        $self->path = $path;

        return $self;
    }

    public function withFile(string $file): self
    {
        $self = clone $this;
        $self->file = $file;

        return $self;
    }

    public function withProgress(string $progress): self
    {
        $self = clone $this;
        $self->progress = $progress;

        return $self;
    }

    public function withNetwork(?string $network = null): self
    {
        $self = clone $this;
        $self->network = $network;

        return $self;
    }

    public function withPlatform(?string $platform = null): self
    {
        $self = clone $this;
        $self->platform = $platform;

        return $self;
    }

    public function withOutput(?string $output = null): self
    {
        $self = clone $this;
        $self->output = $output;

        return $self;
    }

    public function withTarget(?string $target = null): self
    {
        $self = clone $this;
        $self->target = $target;

        return $self;
    }

    /**
     * @param array<string, string|int|float> $args
     */
    public function withArgs(array $args = []): self
    {
        $self = clone $this;
        $self->args = $args;

        return $self;
    }

    public function cmd(): array
    {
        $cmd = ['docker buildx build'];

        $cmd[] = "--tag {$this->tag}";
        $cmd[] = "--file {$this->file}";
        $cmd[] = "--progress {$this->progress}";

        if (null !== $this->network) {
            $cmd[] = "--network {$this->network}";
        }

        if (null !== $this->platform) {
            $cmd[] = "--platform {$this->platform}";
        }

        if (null !== $this->output) {
            $cmd[] = "--output {$this->output}";
        }

        if (null !== $this->target) {
            $cmd[] = "--target {$this->target}";
        }

        $cmd = Util::buildOptions('--build-arg', $this->args, $cmd);

        $cmd[] = $this->path;

        return $cmd;
    }
}
