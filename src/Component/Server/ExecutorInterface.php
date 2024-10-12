<?php

declare(strict_types=1);

namespace App\Component\Server;

interface ExecutorInterface
{
    /**
     * @param class-string                             $task
     * @param array<string, string|string[]|int|int[]> $args
     *
     * @return Result<mixed>
     */
    public function run(string $task, array $args = [], ?callable $onProgress = null): Result;
}
