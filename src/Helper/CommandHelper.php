<?php

declare(strict_types=1);

namespace App\Helper;

use App\Component\TaskRunner\TaskBuilder\DockerCreateService;

use function Amp\File\createDirectory;
use function Amp\File\isDirectory;

final class CommandHelper
{
    public static function generateTlsCertificateLocally(string $namespace, string $domain): DockerCreateService
    {
        $path = getenv('HOME') . '/.mager/certs';
        if (!isDirectory($path)) {
            createDirectory($path, 0755);
        }

        return DockerCreateService::create($namespace, 'generate-tls-cert', 'alpine/mkcert')
            ->withMounts([
                "type=bind,source={$path},destination=/root/.local/share/mkcert",
            ])
            ->withLabels([
                'traefik.enable=false',
            ])
            ->withMode('replicated-job')
            ->withWorkdir('/root/.local/share/mkcert')
            ->withCommand($domain);
    }

    public static function isServiceRunning(string $namespace, string $name, string $mode = 'replicated'): string
    {
        $fullServiceName = "{$namespace}-{$name}";

        return sprintf('docker service ls --format "{{.ID}}" --filter name=%s --filter mode=%s', $fullServiceName, $mode);
    }

    public static function removeService(string $namespace, string $name, string $mode = 'replicated'): string
    {
        $fullServiceName = "{$namespace}-{$name}";

        return sprintf(
            'docker service rm `docker service ls --format "{{.ID}}" --filter name=%s --filter mode=%s`',
            $fullServiceName,
            $mode,
        );
    }
}
