<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

use Swoole\Coroutine;
use Swoole\Coroutine\System;
use Swoole\Timer;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class LocalRunner implements RunnerInterface
{
    public function __construct(
        private SymfonyStyle $io,
        private bool $tty,
    ) {}

    public function run(callable $tasks, bool $showProgress = true, bool $throwError = true): mixed
    {
        $tasks = $tasks();
        while ($tasks->valid()) {
            $task = $tasks->current();
            $label = $tasks->key();

            $cmd = $task;
            if ($task instanceof TaskInterface) {
                $cmd = implode(' ', $task->cmd());
            }

            if (null === $cmd) {
                $tasks->next();
                continue;
            }

            $progress = null;
            if ($showProgress && is_string($label)) {
                $progress = new ProgressIndicator($this->io, 'verbose');
                $progress->start(sprintf(
                    '<fg=green>[%s]</> <fg=yellow>[%s]</> - %s',
                    (new \DateTime('now'))->format('Y-m-d H:i:s'),
                    $this->on(),
                    $label,
                ));
            }

            $timer = Timer::tick(500, fn() => $progress?->advance());

            $wg = new Coroutine\WaitGroup(1);

            /** @var Process $p */
            $p = null;

            go(function () use ($cmd, $progress, $showProgress, $wg, &$p) {
                $process = $this->exec($cmd);

                $cid = go(function () use ($process) {
                    System::waitSignal(SIGINT);
                    if (! $process->isRunning()) {
                        return;
                    }
                    $process->stop(signal: SIGINT);
                });


                try {
                    $process->wait(function () use ($progress, $showProgress) {
                        if ($showProgress && null !== $progress) {
                            $progress->advance();
                        }
                    });
                } catch (ProcessSignaledException|ProcessTimedOutException $e) {
                    $this->io->error($process->getErrorOutput());
                    $this->io->writeln($process->getOutput());
                    throw $e;
                } finally {
                    $p = $process;
                    Coroutine::cancel($cid);
                    $wg->done();
                }
            });

            $wg->wait();
            Timer::clear($timer);

            if (0 !== $p->getExitCode() && $throwError) {
                $tasks->throw(new \Exception($p->getErrorOutput()));
                continue;
            }

            if ($showProgress && null !== $progress) {

                $progress->finish(sprintf(
                    '<fg=green>[%s]</> <fg=yellow>[%s]</> - %s',
                    (new \DateTime('now'))->format('Y-m-d H:i:s'),
                    $this->on(),
                    "{$label} ✔️",
                ));
                $this->io->writeln('');
            }

            $output = $p->getOutput();

            if ($task instanceof SerializedOutputInterface) {
                $output = $task->serialize($output);
            }

            $tasks->send($output);
        }

        return $tasks->getReturn();
    }

    protected function exec(string $cmd): Process
    {
        $process = Process::fromShellCommandline($cmd);

        $process->setTimeout(60 * 30);
        $process->setIdleTimeout(60 * 30);
        $process->setTty($this->tty);
        $process->start();

        return $process;
    }

    protected function on(): string
    {
        return 'localhost';
    }
}
