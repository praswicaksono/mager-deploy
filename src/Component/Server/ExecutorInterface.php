<?php

declare(strict_types=1);

namespace App\Component\Server;

interface ExecutorInterface
{
    /**
     * @template T
     *
     * @param class-string<TaskInterface<T>>           $task
     * @param array<string, string|string[]|int|int[]> $args
     * @param callable(string, string): void|null      $onProgress
     *
     * @return Result<T>
     */
    public function run(string $task, array $args = [], ?callable $onProgress = null): Result;
}
