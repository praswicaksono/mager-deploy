<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

use App\Component\Config\Data\Server;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

final class Util
{
    /**
     * @param array<string|int, mixed> $args
     * @param string[]                 $cmd
     *
     * @return string[]
     */
    public static function buildOptions(string $options, array $args, array $cmd): array
    {
        foreach ($args as $key => $value) {
            $cmd[] = $options;
            if (is_int($key) && !empty($value)) {
                $cmd[] = "{$value}";
            } else {
                $cmd[] = "{$key}={$value}";
            }
        }

        return $cmd;
    }

    /**
     * @template T
     *
     * @param callable(string): T $callback
     *
     * @return Collection<int, T>
     */
    public static function deserializeJsonList(string $json, callable $callback): Collection
    {
        $collection = new ArrayCollection();

        $items = explode(PHP_EOL, $json);
        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }
            $collection->add($callback($item));
        }

        return $collection;
    }

    public static function createSshConnection(Server $server, bool $tty = false): Ssh
    {
        return Ssh::create(
            $server->user,
            $server->ip,
        )->disablePasswordAuthentication()
            ->usePort($server->port)
            ->usePrivateKey($server->keyPath)
            ->disableStrictHostKeyChecking()
            ->disablePasswordAuthentication()
            ->useMultiplexing("/tmp/{$server->user}-{$server->ip}-%C")
            ->configureProcess(function (Process $process) use ($tty) {
                $process->setTty($tty);
                $process->setIdleTimeout(60 * 30);
            })
            ->setTimeout(60 * 30);
    }
}
