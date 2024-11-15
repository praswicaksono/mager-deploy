<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

use Spatie\Ssh\Ssh as BaseSsh;
use Symfony\Component\Process\Process;

class Ssh extends BaseSsh
{
    protected function getExecuteCommandWithTtyInput(string $command): string
    {
        if (in_array($this->host, ['local', 'localhost', '127.0.0.1'])) {
            return $command;
        }

        $this->extraOptions[] = '-t';

        $extraOptions = implode(' ', $this->getExtraOptions());

        $target = $this->getTargetForSsh();

        return "ssh {$extraOptions} {$target} {$command} < /dev/tty";
    }

    public function executeAsyncWithTtyInput(string $command): Process
    {
        $sshCommand = $this->getExecuteCommandWithTtyInput($command);

        return $this->run($sshCommand, 'start');
    }
}
