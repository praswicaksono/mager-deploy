<?php

namespace App\Component\Server\Task;

use App\Component\Server\Docker\DockerServiceId;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<DockerServiceId>
 */
final class DockerServiceCreate implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        // TODO: add update concurrency options and pause on update failure + auto rollback
        $namespace = (string) Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $image = (string) Helper::getArg(Param::DOCKER_SERVICE_IMAGE->value, $args);
        $name = (string) Helper::getArg(Param::DOCKER_SERVICE_NAME->value, $args);
        /** @var array|string[] $constraints */
        $constraints = Helper::getArg(Param::DOCKER_SERVICE_CONSTRAINTS->value, $args, required: false) ?? [];
        /** @var ?string $mode */
        $mode = Helper::getArg(Param::DOCKER_SERVICE_MODE->value, $args, required: false);
        /** @var ?string $updateOrder */
        $updateOrder = Helper::getArg(Param::DOCKER_SERVICE_UPDATE_ORDER->value, $args, required: false);
        /** @var ?string $updateFailureAction */
        $updateFailureAction = Helper::getArg(Param::DOCKER_SERVICE_UPDATE_FAILURE_ACTION->value, $args, required: false);
        /** @var array|string[] $network */
        $network = Helper::getArg(Param::DOCKER_SERVICE_NETWORK->value, $args, required: false) ?? [];
        /** @var ?string $command */
        $command = Helper::getArg(Param::DOCKER_SERVICE_COMMAND->value, $args, required: false);
        /** @var array|string[] $env */
        $env = Helper::getArg(Param::DOCKER_SERVICE_ENV->value, $args, required: false) ?? [];
        /** @var array|string[] $ports */
        $ports = Helper::getArg(Param::DOCKER_SERVICE_PORT_PUBLISH->value, $args, required: false) ?? [];
        /** @var array|string[] $labels */
        $labels = Helper::getArg(Param::DOCKER_SERVICE_LABEL->value, $args, required: false) ?? [];
        /** @var array|string[] $mounts */
        $mounts = Helper::getArg(Param::DOCKER_SERVICE_MOUNT->value, $args, required: false) ?? [];
        /** @var int $replicas */
        $replicas = Helper::getArg(Param::DOCKER_SERVICE_REPLICAS->value, $args, required: false) ?? 1;
        /** @var ?float $limitCpu */
        $limitCpu = Helper::getArg(Param::DOCKER_SERVICE_LIMIT_CPU->value, $args, required: false) ?? null;
        /** @var ?string $limitMemory */
        $limitMemory = Helper::getArg(Param::DOCKER_SERVICE_LIMIT_MEMORY->value, $args, required: false) ?? null;

        $cmd = ['docker', 'service', 'create'];

        $cmd = Helper::buildOptions('--constraint', $constraints, $cmd);
        $cmd = Helper::buildOptions('--network', $network, $cmd);
        $cmd = Helper::buildOptions('--env', $env, $cmd);
        $cmd = Helper::buildOptions('--publish', $ports, $cmd);
        $cmd = Helper::buildOptions('--label', $labels, $cmd);
        $cmd = Helper::buildOptions('--mount', $mounts, $cmd);

        $cmd[] = "--name {$namespace}-{$name}";
        $cmd[] = "--replicas {$replicas}";

        if (null !== $updateFailureAction) {
            $cmd[] = "--update-failure-action {$updateFailureAction}";
        }

        if (null !== $updateOrder) {
            $cmd[] = "--update-order {$updateOrder}";
        }

        if (null !== $mode) {
            $cmd[] = "--mode {$mode}";
        }

        if (null !== $limitCpu) {
            $cmd[] = "--limit-cpu {$limitCpu}";
        }

        if (null !== $limitMemory) {
            $cmd[] = "--limit-memory {$limitMemory}";
        }

        $cmd[] = $image;

        if (null !== $command) {
            $cmd[] = $command;
        }

        return [implode(' ', $cmd)];
    }

    public function result(int $statusCode, string $out, string $err): DockerServiceId
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return new DockerServiceId($out);
    }
}
