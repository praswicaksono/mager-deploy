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

final class Server
{
    private ExecutorInterface $executor;

    public static function withExecutor(ExecutorInterface $executor): Server
    {
        $obj = new Server();
        $obj->executor = $executor;

        return $obj;
    }

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

    public function isTraefikRunning(string $namespace): bool
    {
        /** @var Result<ArrayCollection<DockerService>> $res */
        $res = $this->executor->run(DockerServiceList::class, [
            Param::DOCKER_SERVICE_LIST_FILTER->value => ["name={$namespace}-mager_traefik"]
        ]);

        if ($res->data->isEmpty()) {
            return false;
        }

        return true;
    }

    public function isDnsMasqRunning(string $namespace): bool
    {
        /** @var Result<ArrayCollection<DockerService>> $res */
        $res = $this->executor->run(DockerServiceList::class, [
            Param::DOCKER_SERVICE_LIST_FILTER->value => ["name={$namespace}-mager_dns"]
        ]);

        if ($res->data->isEmpty()) {
            return false;
        }

        return true;
    }
}