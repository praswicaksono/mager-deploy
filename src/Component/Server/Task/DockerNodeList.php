<?php
declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\Docker\DockerNode;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Result;
use App\Component\Server\TaskInterface;
use Doctrine\Common\Collections\ArrayCollection;

final class DockerNodeList implements TaskInterface
{
    public static function exec($args = []): array
    {
        $filterParam = $args[Param::DOCKER_NODE_LIST_FILTER->value] ?? [];

        $filters = [];
        foreach ($filterParam as $key => $value) {
            if ($value === null) {
                $filters[] ="--filter node.label={{$key}}";
            } else {
                $filters[] ="--filter node.label={{$key}}={{$value}}";
            }
        }

        return [
            sprintf('docker node ls --format "{{json .}}" %s', implode(' ', $filters))
        ];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if ($statusCode !== 0) {
            FailedCommandException::throw($err, $statusCode);
        }

        $items = explode(PHP_EOL, $out);
        $collection = new ArrayCollection();
        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }
            $collection->add(DockerNode::fromJsonString($item));
        }

        return $collection;
    }
}