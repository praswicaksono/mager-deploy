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
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Helper\ProgressIndicator;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

final class Server
{
    private ExecutorInterface $executor;

    public static function withExecutor(ExecutorInterface $executor): Server
    {
        $obj = new Server();
        $obj->executor = $executor;

        return $obj;
    }

    /**
     * @param class-string                        $task
     * @param array<string|int, mixed>            $args
     * @param callable(string, string): void|null $onProgress
     *
     * @return ?Result<mixed>
     *
     * @throws FailedCommandException
     */
    public function exec(string $task, array $args = [], ?callable $onProgress = null, bool $continueOnError = false): ?Result
    {
        try {
            return $this->executor->run($task, $args, $onProgress);
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
            $this->executor->run(DockerNodeList::class);
        } catch (FailedCommandException $e) {
            return false;
        }

        return true;
    }

    public function isProxyRunning(string $namespace): bool
    {
        /** @var Result<ArrayCollection<int, DockerService>> $res */
        $res = $this->executor->run(DockerServiceList::class, [
            Param::DOCKER_SERVICE_LIST_FILTER->value => ["name={$namespace}-mager_proxy"],
        ]);

        if ($res->data->isEmpty()) {
            return false;
        }

        return true;
    }

    public function isProxyAutoConfigRunning(string $namespace): bool
    {
        /** @var Result<ArrayCollection<int, DockerService>> $res */
        $res = $this->executor->run(DockerServiceList::class, [
            Param::DOCKER_SERVICE_LIST_FILTER->value => ["name={$namespace}-mager_pac"],
        ]);

        if ($res->data->isEmpty()) {
            return false;
        }

        return true;
    }

    /**
     * @return callable(string, string): void
     */
    public function showOutput(SymfonyStyle $io, bool $debug, ProgressIndicator $progress): callable
    {
        return function (string $type, string $buffer) use ($io, $debug, $progress): void {
            if (Process::ERR === $type) {
                if ($debug) {
                    $io->warning($buffer);
                }
            } else {
                $progress->advance();
                if ($debug) {
                    $io->write($buffer);
                }
            }
        };
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
