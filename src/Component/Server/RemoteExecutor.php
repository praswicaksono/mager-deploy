<?php

declare(strict_types=1);

namespace App\Component\Server;

use Spatie\Ssh\Ssh;
use Symfony\Component\Console\Style\SymfonyStyle;

final readonly class RemoteExecutor implements ExecutorInterface
{
    public function __construct(private string $serverName, private Ssh $conn) {}

    public function run(SymfonyStyle $io, string $task, array $args = [], bool $showOutput = true): Result
    {
        $command = implode(PHP_EOL, $task::exec($args));
        if (str_starts_with($command, 'upload')) {
            [,$srcDest] = explode(';', $command);
            [$src, $dest] = explode(':', $srcDest);
            $process = $this->conn->upload($src, $dest);
        } else {
            $process = $this->conn->executeAsync($command);
        }

        [$outBuffer, $errBuffer] = Helper::handleProcessOutput($this->serverName, $io, $process, $showOutput);

        return new Result((new $task())->result($process->getExitCode(), $outBuffer, $errBuffer));
    }
}
