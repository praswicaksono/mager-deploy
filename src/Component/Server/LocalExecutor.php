<?php

declare(strict_types=1);

namespace App\Component\Server;

use Symfony\Component\Process\Process;

final class LocalExecutor implements ExecutorInterface
{
    public function __construct(private readonly bool $debug = false) {}

    public function run(string $task, array $args = [], ?callable $onProgress = null): Result
    {
        $command = implode(PHP_EOL, $task::exec($args));
        if ($this->debug) {
            fwrite(STDOUT, $command . PHP_EOL);
        }

        $process = Process::fromShellCommandline($command);
        $process->setTimeout(60 * 30);
        $process->start();

        $out = fopen('php://memory', 'r+');
        $err = fopen('php://memory', 'r+');

        $process->wait(function (string $type, string $buffer) use ($out, $err, $onProgress, $process) {
            match (true) {
                Process::ERR == $type => fwrite($err, $buffer),
                default => fwrite($out, $buffer)
            };
            if (is_callable($onProgress)) {
                $onProgress($type, $buffer);
            }

            $process->checkTimeout();
        });

        rewind($out);
        rewind($err);
        $outBuffer = stream_get_contents($out);
        $errBuffer = stream_get_contents($err);

        fclose($out);
        fclose($err);

        return new Result((new $task())->result($process->getExitCode(), $outBuffer, $errBuffer));
    }
}
