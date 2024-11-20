<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

interface RunnerInterface
{
    /**
     * @param callable(): \Generator $tasks
     */
    public function run(callable $tasks, bool $showProgress = true, bool $throwError = true): mixed;
}
