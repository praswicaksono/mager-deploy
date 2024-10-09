<?php
declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;
use Webmozart\Assert\Assert;

final class DockerSwarmInit implements TaskInterface
{
    public static function exec($args = []): array
    {
        $managerIp = $args[Param::DOCKER_SWARM_MANAGER_IP->value];

        return [
            "docker swarm init --advertise-addr {$managerIp}",
        ];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if ($statusCode !== 0) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}