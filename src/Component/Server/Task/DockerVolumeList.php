<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\Docker\DockerVolume;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;
use Doctrine\Common\Collections\Collection;

/**
 * @template T
 *
 * @implements TaskInterface<Collection<int, DockerVolume>>
 * /
 */
final class DockerVolumeList implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $filters = Helper::getArg(Param::DOCKER_VOLUME_LIST_FILTER->value, $args, required: false) ?? [];

        $cmd = ['docker', 'volume', 'ls', '--format "{{json .}}"'];
        $cmd = Helper::buildOptions('--filter', $filters, $cmd);

        return [
            implode(' ', $cmd),
        ];
    }

    /**
     * @return Collection<int, DockerVolume>
     *
     * @throws FailedCommandException
     */
    public function result(int $statusCode, string $out, string $err): Collection
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return Helper::deserializeJsonList($out, function (string $item): DockerVolume {
            return DockerVolume::fromJsonString($item);
        });
    }
}
