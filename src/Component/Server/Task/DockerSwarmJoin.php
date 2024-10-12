<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerSwarmJoin implements TaskInterface
{
    public static function exec($args = []): array
    {
        $token = $args[Param::DOCKER_SWARM_TOKEN->value];
        $managerIp = $args[Param::DOCKER_SWARM_MANAGER_IP->value];

        return [
            "docker swarm join --token {$token} {$managerIp}:2377",
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
