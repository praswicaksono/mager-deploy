<?php

declare(strict_types=1);

namespace App\Component\Server;

final class FailedCommandException extends \Exception
{
    /**
     * @throws static
     */
    public static function throw(string $message, int $code = 0)
    {
        throw new self($message, $code);
    }
}
