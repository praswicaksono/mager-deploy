<?php
declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

final class AddLocalHostFile implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $dir = getenv('HOME');

        return [
            "mkdir -p {$dir}/.mager",
            "echo '127.0.0.1 *.wip' > {$dir}/.mager/hosts",
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