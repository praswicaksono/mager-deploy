<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @template T
 *
 * @implements TaskInterface<string>
 */
final class DockerInspect implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = (string) Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $id = (string) Helper::getArg(Param::DOCKER_INSPECT_ID->value, $args);
        /** @var array|string[] $filter */
        $filter = Helper::getArg(Param::DOCKER_INSPECT_FILTER->value, $args, required: false) ?? [];

        $cmd = ['docker', 'inspect', "{$namespace}-{$id}"];
        $cmd = Helper::buildOptions('--filter', $filter, $cmd);

        return [implode(' ', $cmd)];
    }

    public function result(int $statusCode, string $out, string $err): string
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return $out;
    }
}
