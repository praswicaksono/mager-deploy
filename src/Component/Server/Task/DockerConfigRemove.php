<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DockerConfigRemove implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args, required: false);
        $configName = Helper::getArg(Param::DOCKER_CONFIG_NAME->value, $args, required: false);

        $filter = $namespace . '-';
        if (!empty($configName)) {
            $filter = $configName;
        }

        return [
            sprintf('docker config rm `docker config ls --format "{{.ID}}" --filter name=%s`', $filter),
        ];
    }

    public function result(int $statusCode, string $out, string $err): null
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}
