<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

interface RunnerInterface
{
    public function run(\Generator $tasks, bool $showProgress = true, bool $throwError = true): mixed;
}
