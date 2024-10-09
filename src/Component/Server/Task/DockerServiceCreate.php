<?php

namespace App\Component\Server\Task;

use App\Component\Server\Docker\DockerServiceId;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;
final class DockerServiceCreate implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $image = Helper::getArg(Param::DOCKER_SERVICE_IMAGE->value, $args);
        $name = Helper::getArg(Param::DOCKER_SERVICE_NAME->value, $args);
        $constraints = Helper::getArg(Param::DOCKER_SERVICE_CONSTRAINTS->value, $args, required: false) ?? [];
        $mode = Helper::getArg(Param::DOCKER_SERVICE_MODE->value, $args, required: false);
        $network = Helper::getArg(Param::DOCKER_SERVICE_NETWORK->value, $args, required: false) ?? [];
        $command = Helper::getArg(Param::DOCKER_SERVICE_COMMAND->value, $args, required: false);
        $env = Helper::getArg(Param::DOCKER_SERVICE_ENV->value, $args, required: false) ?? [];
        $ports = Helper::getArg(Param::DOCKER_SERVICE_PORT_PUBLISH->value, $args, required: false) ?? [];
        $labels = Helper::getArg(Param::DOCKER_SERVICE_LABEL->value, $args, required: false) ?? [];
        $mounts = Helper::getArg(Param::DOCKER_SERVICE_MOUNT->value, $args, required: false) ?? [];
        $replicas = Helper::getArg(Param::DOCKER_SERVICE_REPLICAS->value, $args, required: false) ?? 1;

        $cmd = ['docker', 'service', 'create'];

        $cmd = Helper::buildOptions('--constraint', $constraints, $cmd);
        $cmd = Helper::buildOptions('--network', $network, $cmd);
        $cmd = Helper::buildOptions('--env', $env, $cmd);
        $cmd = Helper::buildOptions('--publish', $ports, $cmd);
        $cmd = Helper::buildOptions('--label', $labels, $cmd);
        $cmd = Helper::buildOptions('--mount', $mounts, $cmd);

        $cmd[] = "--name {$namespace}-{$name}";
        $cmd[] = "--detach";
        $cmd[] = "--replicas {$replicas}";

        if ($mode !== null) {
            $cmd[] = "--mode {$mode}";
        }

        $cmd[] = $image;

        if ($command !== null) {
            $cmd[] = $command;
        }

        return [implode(' ', $cmd)];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if ($statusCode !== 0) {
            FailedCommandException::throw($err, $statusCode);
        }

        return new DockerServiceId($out);
    }
}