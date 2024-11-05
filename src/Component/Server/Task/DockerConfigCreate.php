<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

use function Amp\File\exists;

/**
 * @implements TaskInterface<null>
 */
final class DockerConfigCreate implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->name, $args);
        $configFile = Helper::getArg(Param::DOCKER_CONFIG_FILE_PATH->name, $args);

        if (!exists($configFile)) {
            throw new \InvalidArgumentException('Config file does not exist');
        }

        $configName = Helper::extractConfigNameFromPath($namespace, $configFile);

        return [
            "cat {$configFile} | docker config create {$namespace}-{$configName} -",
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
