<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;
use Webmozart\Assert\Assert;

/**
 * @template T
 * @implements TaskInterface<null>
 */
final class DockerNodeUpdate implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $nodeId = (string) Helper::getArg(Param::DOCKER_NODE_ID->value, $args);
        /** @var array|string[] $addLabel */
        $addLabel = Helper::getArg(Param::DOCKER_NODE_UPDATE_LABEL_ADD->value, $args, required: false) ?? [];
        /** @var array|string[] $removeLabel */
        $removeLabel = Helper::getArg(Param::DOCKER_NODE_UPDATE_LABEL_REMOVE->value, $args, required: false) ?? [];
        /** @var ?string $role */
        $role = Helper::getArg(Param::DOCKER_NODE_UPDATE_ROLE->value, $args, required: false) ?? null;
        /** @var ?string $availability */
        $availability = Helper::getArg(Param::DOCKER_NODE_UPDATE_AVAILABILITY->value, $args, required: false) ?? null;

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

    public function result(int $statusCode, string $out, string $err): null
    {
        if (0 != $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}
