<?php
declare(strict_types=1);

namespace App\Component\Server;

interface ExecutorInterface
{
    /**
     * @param class-string<TaskInterface> $task
     * @param array $args
     * @param ?callable $onProgress
     * @return Result
     */
    public function run(string $task, array $args = [], ?callable $onProgress = null): Result;
}