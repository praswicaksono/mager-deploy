<?php
declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

final class DockerVolumeCreate implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $name = Helper::getArg(Param::DOCKER_VOLUME_NAME->value, $args);

        return [
            "docker volume create {$namespace}-{$name}"
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