<?php

declare(strict_types=1);

namespace App\Component\Server;

interface ExecutorInterface
{
    /**
     * @param class-string<TaskInterface<mixed>>       $task
     * @param array<string, string|string[]|int|int[]> $args
     * @param callable(string, string): void|null      $onProgress
     *
     * @return Result<mixed>
     */
    public function run(string $task, array $args = [], ?callable $onProgress = null): Result;
}
