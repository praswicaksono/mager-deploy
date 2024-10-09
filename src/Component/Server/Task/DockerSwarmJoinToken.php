<?php
declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;
use Webmozart\Assert\Assert;
use App\Component\Server\Docker\DockerSwarmJoinToken as Result;

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

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if ($statusCode !== 0) {
            FailedCommandException::throw($err, $statusCode);
        }

        return new Result($out);
    }
}