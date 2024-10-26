<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerServiceRemoveByNamespace implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);

        return [
            sprintf('docker service rm `docker service ls --format "{{.ID}}" --filter name=%s-`', $namespace),
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
