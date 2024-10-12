<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

final class DockerNetworkCreate implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $driver = Helper::getArg(Param::DOCKER_NETWORK_CREATE_DRIVER->value, $args);
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args, required: false) ?? null;
        $name = Helper::getArg(Param::DOCKER_NETWORK_NAME->value, $args);

        if (null !== $namespace) {
            $name = "{$namespace}-{$name}";
        }

        return [
            "docker network create --scope=swarm -d {$driver} {$name}",
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
