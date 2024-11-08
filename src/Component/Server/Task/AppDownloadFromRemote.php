<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\Helper;
use App\Component\Server\TaskInterface;

/**
 * @implements TaskInterface<string>
 */
final class AppDownloadFromRemote implements TaskInterface
{
    private static string $cwd;

    public static function exec(array $args = []): array
    {
        $namespace = Helper::getArg(Param::GLOBAL_NAMESPACE->value, $args);
        $url = Helper::getArg(Param::APP_URL->value, $args);

        return match (true) {
            str_starts_with($url, 'https://github.com') => self::downloadFromGithub($namespace, $url),
            str_starts_with($url, 'file://') => self::symlinkFromLocalFolder($namespace, $url),
            default => throw new \InvalidArgumentException('Invalid URL'),
        };
    }

    /**
     * @return string[]
     */
    private static function downloadFromGithub(string $namespace, string $url): array
    {
        $dir = getenv('HOME') . '/.mager/apps/' . $namespace;
        $appName = explode('/', $url);
        $appName = end($appName);

        self::$cwd = $dir . '/' . $appName;

        return [
            "mkdir -p -m755 {$dir}",
            "curl -L --progress-bar {$url}/archive/refs/heads/master.zip -o '{$dir}/{$appName}.zip'",
            "unzip {$dir}/{$appName}.zip -d {$dir}",
            "mv {$dir}/{$appName}-master {$dir}/{$appName} && chmod -R 755 {$dir}/{$appName}",
            "rm -f {$dir}/{$appName}.zip",
        ];
    }

    /**
     * @return string[]
     */
    private static function symlinkFromLocalFolder(string $namespace, string $url): array
    {
        $dir = getenv('HOME') . '/.mager/apps/' . $namespace;
        $appName = explode('/', $url);
        $appName = end($appName);

        self::$cwd = $dir . '/' . $appName;

        $url = str_replace('file://', '/', $url);

        return [
            "mkdir -p {$dir}",
            "ln -sf {$url} {$dir}/{$appName}",
        ];
    }

    public function result(int $statusCode, string $out, string $err): string
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return self::$cwd;
    }
}
