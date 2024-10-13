<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerSwarmInit implements TaskInterface
{
    public static function exec($args = []): array
    {
        $managerIp = $args[Param::DOCKER_SWARM_MANAGER_IP->value];

        return [
            "docker swarm init --advertise-addr {$managerIp}",
        ];
    }

    public function result(int $statusCode, string $out, string $err): null
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}
