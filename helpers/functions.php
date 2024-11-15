<?php
declare(strict_types=1);

use App\Component\Config\Data\Server;
use App\Component\Config\Json;
use App\Component\TaskRunner\RunnerBuilder;
use App\Component\TaskRunner\TaskInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

function run(callable|string|TaskInterface $command, string|Server $on, $local = false, $managerOnly = false, $workerOnly = false, $showProgress = true, $throwError = true, $tty = false): mixed
{
    $io = new SymfonyStyle(
        new StringInput(''),
        new ConsoleOutput()
    );
    $home = getenv('HOME');

    $runner = RunnerBuilder::create()
        ->withIO($io)
        ->withTty($tty)
        ->withConfig(Json::fromFile("{$home}/.mager/config.json"));

    if ($managerOnly) {
        $runner = $runner->onManagerOnly(true)->onSingleManagerServer(true);
    }

    if ($workerOnly) {
        $runner = $runner->onWorkerOnly(true);
    }

    if ($on instanceof Server) {
        $runner = $runner->withServer($on);
        $on = get_class($on);
    }

    $runner = $runner->build($on, local: $local);

    $generator = match(true) {
        $command instanceof TaskInterface || is_string($command) => (fn() => yield $command)(),
        default => $command()
    };

    return $runner->run($generator, showProgress: $showProgress, throwError: $throwError);
}

function runLocally(callable|string|TaskInterface $command, $showProgress = true, $throwError = true, $tty = false): mixed {
    return run($command, 'local', local: true, showProgress: $showProgress, throwError: $throwError, tty: $tty);
}

function runOnManager(callable|string|TaskInterface $command, string $on, $showProgress = true, $throwError = true): mixed {
    return run($command, $on, managerOnly: true, showProgress: $showProgress, throwError: $throwError);
}

function runOnWorker(callable|string|TaskInterface $command, string $on, $showProgress = true, $throwError = true): void {
    run($command, $on, workerOnly: true, showProgress: $showProgress, throwError: $throwError);
}

function runOnServer(callable|string|TaskInterface $command, Server $on, $showProgress = true, $throwError = true): mixed
{
    return run($command, $on, showProgress: $showProgress, throwError: $throwError);
}

function runOnServerWithTty(string $command, Server $on, $throwError = true): void
{
    $command = fn() => yield $command;
    run($command, $on, showProgress: false, throwError: $throwError, tty: true);
}