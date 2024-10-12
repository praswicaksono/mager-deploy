<?php

declare(strict_types=1);

namespace App\Component\Server;

/**
 * @template T
 */
interface TaskInterface
{
    /**
     * @param array<string, string|string[]|int|int[]|null> $args
     *
     * @return string[]
     */
    public static function exec(array $args = []): array;

    /**
     * @return T
     */
    public function result(int $statusCode, string $out, string $err): mixed;
}
