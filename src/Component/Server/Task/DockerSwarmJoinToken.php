<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;
use Webmozart\Assert\Assert;

/**
 * @template T
 *
 * @implements TaskInterface<string>
 */
final class DockerSwarmJoinToken implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $validType = ['manager', 'worker'];
        $type = $args[Param::DOCKER_SWARM_TOKEN_TYPE->value];

        Assert::inArray($type, $validType);

        return [
            "docker swarm join-token -q {$type}",
        ];
    }

    public function result(int $statusCode, string $out, string $err): string
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return $out;
    }
}
