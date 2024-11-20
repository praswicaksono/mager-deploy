<?php
declare(strict_types=1);

use App\Component\Config\Data\Server;
use App\Component\Config\Json;
use App\Component\TaskRunner\RunnerBuilder;
use App\Component\TaskRunner\TaskInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

function run(callable|string|TaskInterface $command, string|Server|Collection $on, $local = false, $managerOnly = false, $workerOnly = false, $showProgress = true, $throwError = true, $tty = false): mixed
{
    $io = new SymfonyStyle(
        new StringInput(''),
        new ConsoleOutput()
    );
    $home = getenv('HOME');

    $runner = RunnerBuilder::create()
        ->withIO($io)
        ->withTty($tty)
        ->withConfig(Json::fromFile("{$home}/.mager/config.json"))
        ->onManagerOnly($managerOnly)->onSingleManagerServer($managerOnly)
        ->onWorkerOnly($workerOnly);

    if ($on instanceof Server) {
        $runner = $runner->withServer($on);
        $on = get_class($on);
    }

    if ($on instanceof Collection) {
        $runner = $runner->withServerCollection($on);
        $on = get_class($on);
    }

    $runner = $runner->build($on, local: $local);

    $generator = match(true) {
        $command instanceof TaskInterface || is_string($command) => (fn() => yield $command),
        default => $command
    };

    return $runner->run($generator, showProgress: $showProgress, throwError: $throwError);
}

function runLocally(callable|string|TaskInterface $command, bool $showProgress = true, bool $throwError = true, bool $tty = false): mixed {
    return run($command, 'local', local: true, showProgress: $showProgress, throwError: $throwError, tty: $tty);
}

function runOnManager(callable|string|TaskInterface $command, string $on, bool $showProgress = true, bool $throwError = true, bool $tty = false): mixed {
    return run($command, $on, managerOnly: true, showProgress: $showProgress, throwError: $throwError, tty: $tty);
}

function runOnWorker(callable|string|TaskInterface $command, string $on, bool $showProgress = true, bool $throwError = true): void {
    run($command, $on, workerOnly: true, showProgress: $showProgress, throwError: $throwError);
}

function runOnAllServerInNamespace(callable|string|TaskInterface $command, string $on, bool $showProgress = true, bool $throwError = true): void {
    run($command, $on, showProgress: $showProgress, throwError: $throwError);
}

function runOnServerCollection(callable|string|TaskInterface $command, Collection $on): void
{
    run($command, $on);
}

function runOnServer(callable|string|TaskInterface $command, Server $on, bool $showProgress = true, bool $throwError = true): mixed
{
    return run($command, $on, showProgress: $showProgress, throwError: $throwError);
}

function runOnServerWithTty(string $command, Server $on, bool $throwError = true): void
{
    $command = fn() => yield $command;
    run($command, $on, showProgress: false, throwError: $throwError, tty: true);
}