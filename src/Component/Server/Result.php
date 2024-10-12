<?php

declare(strict_types=1);

namespace App\Component\Server;

/**
 * @template T
 */
final class Result
{
    /**
     * @param ?T $data
     */
    public function __construct(public readonly ?object $data = null) {}
}
