<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class DockerSwarmJoinToken
{
    public function __construct(public string $token) {}
}
