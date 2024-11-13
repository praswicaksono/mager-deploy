<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

use App\Component\Config\Data\Server;
use Spatie\Ssh\Ssh;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class SingleRemoteRunner extends LocalRunner implements RunnerInterface
{
    public function __construct(
        /** @phpstan-ignore property.onlyWritten */
        private SymfonyStyle $io,
        private Server $server,
    ) {
        parent::__construct($io);
    }

    protected function exec(string $cmd): Process
    {
        $ssh = Util::createSshConnection($this->server);

        @[$customCommand, $arg] = explode(' ', $cmd);

        return match (true) {
            'upload' === $customCommand => $this->upload($ssh, $arg),
            default => $ssh->executeAsync($cmd),
        };
    }

    private function upload(Ssh $ssh, string $arg): Process
    {
        [$src, $dest] = explode(':', $arg);

        return $ssh->upload($src, $dest);
    }
}
