<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\Docker\DockerService;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;
use Doctrine\Common\Collections\Collection;

/**
 * @implements TaskInterface<Collection<int, DockerService>>
 */
final class DockerServiceList implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $filters = Helper::getArg(Param::DOCKER_SERVICE_LIST_FILTER->value, $args, required: false) ?? [];

        $cmd = ['docker service ls --format "{{json .}}"'];
        $cmd = Helper::buildOptions('--filter', $filters, $cmd);

        return [implode(' ', $cmd)];
    }

    /**
     * @return Collection<int, DockerService>
     *
     * @throws FailedCommandException
     */
    public function result(int $statusCode, string $out, string $err): Collection
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return Helper::deserializeJsonList($out, function (string $item): DockerService {
            return DockerService::fromJsonString($item);
        });
    }
}
