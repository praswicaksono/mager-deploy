<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;
use Webmozart\Assert\Assert;

final class DockerNodeUpdate implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $addLabel = $args[Param::DOCKER_NODE_UPDATE_LABEL_ADD->value] ?? [];
        $removeLabel = $args[Param::DOCKER_NODE_UPDATE_LABEL_REMOVE->value] ?? [];
        $role = $args[Param::DOCKER_NODE_UPDATE_ROLE->value] ?? null;
        $availability = $args[Param::DOCKER_NODE_UPDATE_AVAILABILITY->value] ?? null;
        $nodeId = $args[Param::DOCKER_NODE_ID->value] ?? null;

        $cmd = ['docker', 'node', 'update'];

        $cmd = Helper::buildOptions('--label-add', $addLabel, $cmd);
        $cmd = Helper::buildOptions('--label-rm', $removeLabel, $cmd);

        if (null !== $role) {
            Assert::inArray($role, ['worker', 'manager']);
            $cmd[] = "--role {$role}";
        }

        if (null !== $availability) {
            Assert::inArray($availability, ['active', 'pause', 'drain']);
            $cmd[] = "--availability {$availability}";
        }

        $cmd[] = $nodeId;

        return [implode(' ', $cmd)];

    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if (0 != $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}
