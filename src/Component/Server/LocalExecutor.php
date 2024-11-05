<?php

declare(strict_types=1);

namespace App\Component\Server;

use App\Component\Server\Task\Param;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final readonly class LocalExecutor implements ExecutorInterface
{
    public function __construct(private string $serverName) {}

    public function run(SymfonyStyle $io, string $task, array $args = [], bool $showOutput = true): Result
    {
        $command = implode(PHP_EOL, $task::exec($args));
        $io->writeln($command);

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(60 * 30);
        $process->start();

        $progressName = Helper::getArg(Param::GLOBAL_PROGRESS_NAME->value, $args, required: false) ?? null;
        [$outBuffer, $errBuffer] = Helper::handleProcessOutput($this->serverName, $io, $process, $showOutput, $progressName);

        return new Result((new $task())->result($process->getExitCode(), $outBuffer, $errBuffer));
    }
}
