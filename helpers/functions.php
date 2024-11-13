<?php
declare(strict_types=1);

use App\Component\Config\Json;
use App\Component\TaskRunner\RunnerBuilder;
use App\Component\TaskRunner\TaskInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Style\SymfonyStyle;

function run(callable|string|TaskInterface $command, string $on, $local = false, $managerOnly = false, $workerOnly = false, $showProgress = true, $throwError = true): mixed
{
    $io = new SymfonyStyle(
        new StringInput(''),
        new ConsoleOutput()
    );
    $home = getenv('HOME');

    $runner = RunnerBuilder::create()
        ->withIO($io)
        ->withConfig(Json::fromFile("{$home}/.mager/config.json"));

    if ($managerOnly) {
        $runner = $runner->onManagerOnly(true)->onSingleManagerServer(true);
    }

    if ($workerOnly) {
        $runner = $runner->onWorkerOnly(true);
    }

    $runner = $runner->build($on, local: $local);

    $generator = match(true) {
        $command instanceof TaskInterface || is_string($command) => (fn() => yield $command)(),
        default => $command()
    };

    return $runner->run($generator, showProgress: $showProgress, throwError: $throwError);
}

function runLocally(callable|string|TaskInterface $command, $showProgress = true, $throwError = true): mixed {
    return run($command, 'local', local: true, showProgress: $showProgress, throwError: $throwError);
}

function runOnManager(callable|string|TaskInterface $command, string $on, $showProgress = true, $throwError = true): mixed {
    return run($command, $on, managerOnly: true, showProgress: $showProgress, throwError: $throwError);
}

function runOnWorker(callable|string|TaskInterface $command, string $on, $showProgress = true, $throwError = true): mixed {
    return run($command, $on, workerOnly: true, showProgress: $showProgress, throwError: $throwError);
}