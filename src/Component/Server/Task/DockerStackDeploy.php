<?php
declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

final class DockerStackDeploy implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $appName = Helper::getArg(Param::DOCKER_STACK_DEPLOY_APP_NAME->value, $args);
        $composeFile = Helper::getArg(Param::DOCKER_STACK_DEPLOY_COMPOSE_FILE->value, $args, required: false);

        $cmd = ['docker', 'stack', 'deploy'];
        if (null !== $composeFile) {
            $cmd = Helper::buildOptions('--compose-file', $composeFile, $cmd);
        }

        $cmd[] = "--detach=false {$namespace}-{$appName}";

        return [implode(' ', $cmd)];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if ($statusCode !== 0) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}