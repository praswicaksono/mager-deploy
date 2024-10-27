<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerServiceRemoveByServiceName implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $id = Helper::getArg(Param::DOCKER_SERVICE_NAME->value, $args);

        return [
            sprintf('docker service rm %s', $id),
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
