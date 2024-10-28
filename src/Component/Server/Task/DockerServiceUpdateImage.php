<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerServiceUpdateImage implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = (string) Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $image = (string) Helper::getArg(Param::DOCKER_SERVICE_IMAGE->value, $args);
        $name = (string) Helper::getArg(Param::DOCKER_SERVICE_NAME->value, $args);

        return [
            implode(' ', ['docker', 'service', 'update', '--image', $image, '--force', "{$namespace}-{$name}"]),
        ];
    }

    public function result(int $statusCode, string $out, string $err): null
    {
        if (0 != $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}