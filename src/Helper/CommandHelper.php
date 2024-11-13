<?php

declare(strict_types=1);

namespace App\Helper;

use App\Component\TaskRunner\TaskBuilder\DockerCreateService;

use function Amp\File\createDirectory;
use function Amp\File\isDirectory;

final class CommandHelper
{
    public static function generateTlsCertificateLocally(string $namespace, string $domain): \Generator
    {
        $path = getenv('HOME') . '/.mager/certs';
        if (!isDirectory($path)) {
            createDirectory($path, 0755);
        }

        $uid = trim(yield 'id -u');
        $guid = trim(yield 'id -g');

        yield DockerCreateService::create($namespace, 'generate-tls-cert', 'dcagatay/mkcert')
            ->withMounts([
                "type=bind,source={$path},destination=/certs",
            ])
            ->withLabels([
                'traefik.enable=false',
            ])
            ->withMode('replicated-job')
            ->withEnvs([
                "UID={$uid}",
                "GID={$guid}",
            ])
            ->withCommand($domain);
    }

    public static function isServiceRunning(string $namespace, string $name, string $mode = 'replicated'): string
    {
        $fullServiceName = "{$namespace}-{$name}";

        return sprintf('docker service ls --format "{{.ID}}" --filter name=%s --filter mode=%s', $fullServiceName, $mode);
    }

    public static function removeService(string $namespace, string $name, string $mode = 'replicated'): \Generator
    {
        $fullServiceName = "{$namespace}-{$name}";

        yield sprintf(
            'docker service rm `docker service ls --format "{{.ID}}" --filter name=%s --filter mode=%s`',
            $fullServiceName,
            $mode,
        );
    }

    public static function transferAndLoadImage(string $namespace, string $imageName): \Generator
    {
        yield "upload /tmp/{$namespace}-{$imageName}.tar.gz:/tmp/{$namespace}-{$imageName}.tar.gz";
        yield "docker load < /tmp/{$namespace}-{$imageName}.tar.gz";
        yield "rm -f /tmp/{$namespace}-{$imageName}.tar.gz";
        yield 'docker image prune -a';
    }

    public static function ensureServerArePrepared(string $namespace): \Generator
    {
        $node = yield 'docker node ls';
        if (empty($node)) {
            return false;
        }

        if (empty(yield CommandHelper::isServiceRunning($namespace, 'mager_proxy'))) {
            return false;
        }

        return true;
    }
}
