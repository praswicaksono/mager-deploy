<?php
declare(strict_types=1);

namespace App\Component\Server;

use Spatie\Ssh\Ssh;
use Symfony\Component\Process\Process;

final class RemoteExecutor implements ExecutorInterface
{
    public function __construct(private readonly Ssh $conn)
    {}

    /**
     * @inheritDoc
     */
    public function run(string $task, array $args = [], ?callable $onProgress = null): Result
    {
        $process = $this->conn->executeAsync($task::exec());
        $out = fopen('php://memory', 'r+');
        $err = fopen('php://memory', 'r+');

        $process->wait(function (string $type, string $buffer) use ($out, $err, $onProgress) {
            match (true) {
                $type == Process::OUT => fwrite($out, $buffer),
                $type == Process::ERR => fwrite($err, $buffer),
            };
            if (is_callable($onProgress)) {
                $onProgress($type, $buffer);
            }
        });

        rewind($out);
        rewind($err);
        $outBuffer = stream_get_contents($out);
        $errBuffer = stream_get_contents($err);

        fclose($out);
        fclose($err);

        return new Result((new $task)->result($process->getExitCode(), $outBuffer, $errBuffer));
    }
}