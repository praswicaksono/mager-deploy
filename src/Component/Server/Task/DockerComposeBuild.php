<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

/**
 * @template T
 * @implements TaskInterface<null>
 */
final class DockerComposeBuild implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        return ['docker compose build --no-cache -q'];
    }

    public function result(int $statusCode, string $out, string $err): null
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}
