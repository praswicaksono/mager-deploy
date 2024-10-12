<?php

declare(strict_types=1);

namespace App\Component\Server\Docker;

final class DockerSwarmJoinToken
{
    public function __construct(public readonly string $token) {}
}
