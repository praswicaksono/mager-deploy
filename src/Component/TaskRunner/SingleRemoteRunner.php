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

        [$cmd] = explode(' ', $cmd, 1);

        return match (true) {
            'upload' === $cmd => $this->upload($ssh, $cmd),
            default => $ssh->executeAsync($cmd),
        };
    }

    private function upload(Ssh $ssh, string $cmd): Process
    {
        [, $arguments] = explode(' ', $cmd);
        [$src, $dest] = explode(':', $arguments);

        return $ssh->upload($src, $dest);
    }
}
