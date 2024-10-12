<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

final class AddProxyAutoConfiguration implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        $dir = getenv('HOME');

        $pac = <<<PAC
function FindProxyForURL(url, host) {
  if (shExpMatch(host, "*.wip")) {
    return "PROXY 127.0.0.1:80";
  }
  return "DIRECT";
}
PAC;

        return [
            "mkdir -p {$dir}/.mager",
            "echo '{$pac}' > {$dir}/.mager/proxy.pac",
        ];
    }

    public function result(int $statusCode, string $out, string $err): ?object
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}
