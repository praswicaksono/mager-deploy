<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

/**
 * @template T
 */
interface SerializedOutputInterface
{
    /**
     * @return T
     */
    public function serialize(string $consoleOutput): mixed;
}
