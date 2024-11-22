<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

use App\Component\Config\Data\Server;
use Doctrine\Common\Collections\Collection;
use Swoole\Coroutine\WaitGroup;
use Symfony\Component\Console\Style\SymfonyStyle;

final class SwooleMultiRemoteRunner implements RunnerInterface
{
    /**
     * @param Collection<int, Server> $servers
     */
    public function __construct(
        private readonly SymfonyStyle $io,
        private readonly Collection $servers,
        private readonly int $concurrency = 5,
    ) {}

    public function run(callable $tasks, bool $showProgress = true, bool $throwError = true): bool
    {
        $wg = new WaitGroup();
        foreach ($this->servers as $server) {
            go(function () use ($server, $wg, $tasks, $showProgress, $throwError) {
                $wg->add();
                defer(fn () => $wg->done());

                $runner = new SingleRemoteRunner($this->io, false, $server);

                $runner->run($tasks, $showProgress, $throwError);
            });

            if ($wg->count() === $this->concurrency) {
                $wg->wait();
            }
        }

        if (0 !== $wg->count()) {
            $wg->wait();
        }

        return true;
    }
}
