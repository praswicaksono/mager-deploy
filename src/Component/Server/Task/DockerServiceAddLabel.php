<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerServiceAddLabel implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        /** @var string $namespace */
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        /** @var string $name */
        $name = Helper::getArg(Param::DOCKER_SERVICE_NAME->value, $args);
        /** @var string[] $labels */
        $labels = Helper::getArg(Param::DOCKER_SERVICE_LABEL->value, $args);

        $cmd = ['docker', 'service', 'update'];

        $cmd = Helper::buildOptions('--label-add', $labels, $cmd);

        $cmd[] = "{$namespace}-{$name}";

        return [
            implode(' ', $cmd),
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
