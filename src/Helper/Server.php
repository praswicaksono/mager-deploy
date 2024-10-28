<?php

declare(strict_types=1);

namespace App\Helper;

use App\Component\Server\Docker\DockerService;
use App\Component\Server\ExecutorInterface;
use App\Component\Server\FailedCommandException;
use App\Component\Server\Result;
use App\Component\Server\Task\DockerNodeList;
use App\Component\Server\Task\DockerServiceCreate;
use App\Component\Server\Task\DockerServiceList;
use App\Component\Server\Task\Param;
use App\Component\Server\TaskInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Amp\File\createDirectory;
use function Amp\File\isDirectory;

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
            $this->exec(DockerNodeList::class);
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
        $res = $this->exec(DockerServiceList::class, [
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

    public function generateTLSCertificate(string $namespace, string $domain): void
    {
        $path = getenv('HOME') . '/.mager/certs';
        if (!isDirectory($path)) {
            createDirectory($path, 0755);
        }

        $this->exec(DockerServiceCreate::class, [
            Param::GLOBAL_NAMESPACE->value => $namespace,
            Param::DOCKER_SERVICE_NAME->value => 'generate-tls-cert',
            Param::DOCKER_SERVICE_IMAGE->value => 'alpine/mkcert',
            Param::DOCKER_SERVICE_MOUNT->value => [
                "type=bind,source={$path},destination=/root/.local/share/mkcert",
            ],
            Param::DOCKER_SERVICE_LABEL->value => ['traefik.enable=false'],
            Param::DOCKER_SERVICE_MODE->value => 'replicated-job',
            Param::DOCKER_SERVICE_COMMAND->value => $domain,
            Param::DOCKER_SERVICE_WORKDIR->value => '/root/.local/share/mkcert',
        ]);
    }

    public function registerTLSCertificate(string $namespace, string $domain): void {}
}
