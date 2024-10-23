<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerCleanupJob implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        return [
            'docker service rm `docker service ls --format "{{.ID}}" --filter mode=replicated-job`',
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
