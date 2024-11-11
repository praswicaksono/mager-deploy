<?php

declare(strict_types=1);

namespace App\Component\TaskRunner;

use App\Component\Config\Data\Server;
use App\Component\TaskRunner\Remote\Execute;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Amp\Future\await;
use function Amp\Parallel\Worker\submit;

final class AmpPhpParallelRemoteRunner implements RunnerInterface
{
    /**
     * @param ArrayCollection<int, Server> $servers
     */
    public function __construct(
        private SymfonyStyle $io,
        private ArrayCollection $servers,
    ) {}

    public function run(\Generator $tasks, bool $showProgress = true, bool $throwError = true): mixed
    {
        while ($tasks->valid()) {
            $task = $tasks->current();

            $cmd = $task;
            if ($task instanceof TaskInterface) {
                $cmd = implode(' ', $task->cmd());
            }

            $executions = [];
            foreach ($this->servers as $server) {
                $executions[] = submit(new Execute($server, $cmd))->getFuture();
            }

            $results = await($executions);

            foreach ($results as $result) {
                [$exitCode, $output, $errorOutput] = $result;

                if (0 !== $exitCode && $throwError) {
                    $this->io->error($errorOutput);
                    $this->io->writeln($output);
                    $tasks->throw(new \Exception($errorOutput));
                }
            }

            $tasks->send(null);
        }

        return $tasks->getReturn();
    }
}
