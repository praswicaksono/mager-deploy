<?php

declare(strict_types=1);

namespace App\Component\Server;

use Symfony\Component\Console\Style\SymfonyStyle;

interface ExecutorInterface
{
    /**
     * @template T
     *
     * @param class-string<TaskInterface<T>>           $task
     * @param array<string, string|string[]|int|int[]> $args
     *
     * @return Result<T>
     */
    public function run(SymfonyStyle $io, string $task, array $args = [], bool $showOutput = true): Result;
}
