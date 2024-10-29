<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerImageSave implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $name = Helper::getArg(Param::DOCKER_IMAGE_NAME->value, $args);
        $tag = Helper::getArg(Param::DOCKER_IMAGE_TAG->value, $args);

        return [
            "docker save {$tag} | gzip > /tmp/{$namespace}-{$name}.tar.gz",
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
