<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class DockerServiceId
{
    public function __construct(public string $id) {}
}
