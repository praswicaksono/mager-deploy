<?php

declare(strict_types=1);

namespace App\Component\TaskRunner\Remote;

use Amp\Cancellation;
use Amp\Parallel\Worker\Task;
use Amp\Sync\Channel;
use App\Component\Config\Data\Server;
use App\Component\TaskRunner\Util;
use Spatie\Ssh\Ssh;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

/** @phpstan-ignore missingType.generics */
final class Execute implements Task
{
    private Ssh $ssh;

    private SymfonyStyle $io;

    public function __construct(
        private readonly Server $server,
        private readonly string $cmd,
    ) {}

    public function run(Channel $channel, Cancellation $cancellation): mixed
    {
        $this->ssh = Util::createSshConnection($this->server);
        $this->io = new SymfonyStyle(new StringInput(''), new StreamOutput(STDOUT));

        [$cmd] = explode(' ', $this->cmd, 1);

        $process = match (true) {
            'upload' === $cmd => $this->upload(),
            default => $this->ssh->executeAsync($this->cmd),
        };

        $process->wait(function (string $type, string $buffer): void {
            if (Process::OUT === $type) {
                $this->io->info("[{$this->server->ip}] " . $buffer);
            } else {
                $this->io->warning("[{$this->server->ip}] " . $buffer);
            }

        });

        return [$process->getExitCode(), $process->getOutput(), $process->getErrorOutput()];
    }

    private function upload(): Process
    {
        [, $arguments] = explode(' ', $this->cmd);
        [$src, $dest] = explode(':', $arguments);

        return $this->ssh->upload($src, $dest);
    }
}
