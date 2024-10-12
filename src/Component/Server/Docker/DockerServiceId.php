<?php

declare(strict_types=1);

namespace App\Component\Server\Docker;

final class DockerServiceId
{
    public function __construct(public readonly string $id) {}
}
