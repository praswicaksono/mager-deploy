<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

final class DockerSwarmLeave implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        return [
            'docker swarm leave --force',
        ];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}
