<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @template T
 * @implements TaskInterface<null>
 */
final class DockerImageBuild implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $tag = Helper::getArg(Param::DOCKER_IMAGE_TAG->value, $args);
        $target = Helper::getArg(Param::DOCKER_IMAGE_TARGET->value, $args, required: false) ?? null;
        $output = Helper::getArg(Param::DOCKER_IMAGE_OUTPUT->value, $args, required: false) ?? null;


        $cmd = ['docker', 'buildx', 'build'];
        $cmd[] = "--tag {$tag}";

        if (null !== $target) {
            $cmd[] = "--target {$target}";
        }

        if (null !== $output) {
            $cmd[] = "--output {$output}";
        }

        $cmd[] = '.';

        return [
            implode(' ', $cmd),
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
