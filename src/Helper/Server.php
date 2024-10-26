<?php

declare(strict_types=1);

namespace App\Helper;

use App\Component\Server\Docker\DockerService;
use App\Component\Server\ExecutorInterface;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Result;
use App\Component\Server\Task\DockerNodeList;
use App\Component\Server\Task\DockerServiceList;
use App\Component\Server\Task\Param;
use App\Component\Server\TaskInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Style\SymfonyStyle;

final class Server
{
    private ExecutorInterface $executor;

    private SymfonyStyle $io;

    public static function withExecutor(ExecutorInterface $executor, SymfonyStyle $io): Server
    {
        $obj = new Server();
        $obj->executor = $executor;
        $obj->io = $io;

        return $obj;
    }

    /**
     * @template T
     *
     * @param class-string<TaskInterface<T>> $task
     * @param array<string|int, mixed>       $args
     *
     * @return ?Result<T>
     *
     * @throws FailedCommandException
     */
    public function exec(string $task, array $args = [], bool $continueOnError = false, bool $showOutput = true): ?Result
    {
        try {
            return $this->executor->run($this->io, $task, $args, $showOutput);
        } catch (FailedCommandException $e) {
            if ($continueOnError) {
                return null;
            }

            throw $e;
        }
    }

    public function isDockerSwarmEnabled(): bool
    {
        try {
            $this->executor->run($this->io, DockerNodeList::class);
        } catch (FailedCommandException $e) {
            return false;
        }

        return true;
    }

    public function isProxyRunning(string $namespace): bool
    {
        return $this->isServiceRunning("{$namespace}-mager_proxy");
    }

    public function isServiceRunning(string $containerName): bool
    {
        /** @var Result<ArrayCollection<int, DockerService>> $res */
        $res = $this->executor->run($this->io, DockerServiceList::class, [
            Param::DOCKER_SERVICE_LIST_FILTER->value => [
                "name={$containerName}",
                'mode=replicated',
            ],
        ]);

        if ($res->data->isEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function getAppNameAndCwd(): array
    {
        $cwd = getcwd();
        $cwdArr = explode('/', $cwd);

        return [end($cwdArr), $cwd];
    }
}
