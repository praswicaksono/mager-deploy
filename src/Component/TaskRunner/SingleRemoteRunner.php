<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

use App\Component\Config\Data\Server;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class SingleRemoteRunner extends LocalRunner implements RunnerInterface
{
    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private SymfonyStyle $io,
        private bool $tty,
        private Server $server,
    ) {
        parent::__construct($io, $tty);
    }

    protected function exec(string $cmd): Process
    {
        $ssh = Util::createSshConnection($this->server, $this->tty);

        @[$customCommand, $arg] = explode(' ', $cmd);

        return match (true) {
            'upload' === $customCommand => $this->upload($ssh, $arg),
            default => $this->execute($ssh, $cmd),
        };
    }

    protected function on(): string
    {
        return $this->server->hostname ?? $this->server->ip;
    }

    private function upload(Ssh $ssh, string $arg): Process
    {
        [$src, $dest] = explode(':', $arg);

        $cmd = $ssh->getUploadCommand($src, $dest);

        return parent::exec($cmd);
    }

    private function execute(Ssh $ssh, string $cmd): Process
    {
        if ($this->tty) {
            return $ssh->executeAsyncWithTtyInput($cmd);
        }

        return $ssh->executeAsync($cmd);
    }
}
