<?php
declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<null>
 */
final class DebianUpdatePackage implements TaskInterface
{
    public static function exec($args = []): array
    {
        return [
            'export DEBIAN_FRONTEND=noninteractive',
            'sudo apt update',
            'sudo apt -o Dpkg::Options::="--force-confold" upgrade -q -y --force-yes',
            'sudo apt autoremove -q -y',
        ];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if ($statusCode !== 0) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}