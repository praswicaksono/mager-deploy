<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

interface TaskInterface
{
    /**
     * @return string[]
     */
    public function cmd(): array;
}
