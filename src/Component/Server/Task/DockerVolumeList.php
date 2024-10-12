<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\Docker\DockerVolume;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;
use Doctrine\Common\Collections\ArrayCollection;

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

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        $items = explode(PHP_EOL, $out);
        $collection = new ArrayCollection();
        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }
            $collection->add(DockerVolume::fromJsonString($item));
        }

        return $collection;
    }
}
