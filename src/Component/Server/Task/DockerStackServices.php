<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\Docker\DockerService;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

final class DockerStackServices implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $stackName = Helper::getArg(Param::DOCKER_STACK_DEPLOY_APP_NAME->value, $args);

        return [
            "docker stack services --format '{{json .}}' {$namespace}-{$stackName}",
        ];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return Helper::deserializeJsonList($out, function (string $item) {
            return DockerService::fromJsonString($item);
        });
    }
}
