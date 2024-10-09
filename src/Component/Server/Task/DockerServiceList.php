<?php
declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\Docker\DockerService;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

final class DockerServiceList implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $filters = Helper::getArg(Param::DOCKER_SERVICE_LIST_FILTER->value, $args, required: false) ?? [];

        $cmd = ['docker service ls --format "{{json .}}"'];
        $cmd = Helper::buildOptions('--filter', $filters, $cmd);

        return [implode(' ', $cmd)];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if ($statusCode !== 0) {
            FailedCommandException::throw($err, $statusCode);
        }

        return Helper::deserializeJsonList($out, function (string $item) {
            return DockerService::fromJsonString($item);
        });
    }
}