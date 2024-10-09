<?php
declare(strict_types=1);

namespace App\Component\Server;

/**
 * @template T
 */
interface TaskInterface
{
    public static function exec(array $args = []): array;

    /**
     * @param int $statusCode
     * @param string $out
     * @param string $err
     * @return ?T
     */
    public function result(int $statusCode, string $out, string $err): ?object;
}