<?php

declare(strict_types=1);

namespace App\Helper;

use App\Component\TaskRunner\TaskBuilder\DockerCreateService;

final class CommandHelper
{
    public static function generateTlsCertificateLocally(string $namespace, string $domain): \Generator
    {
        $path = getenv('HOME') . '/.mager/certs';
        if (!is_dir($path)) {
            mkdir($path, 0755);
        }

        $uid = trim(yield 'id -u');
        $guid = trim(yield 'id -g');

        yield "Generating TLS Certificate for {$domain}" => DockerCreateService::create($namespace, 'generate-tls-cert', 'dcagatay/mkcert')
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

    public static function isServiceRunning(string $namespace, string $name): string
    {
        $fullServiceName = "{$namespace}-{$name}";

        return sprintf('docker service ps --format "{{.ID}}" %s', $fullServiceName);
    }

    public static function removeService(string $namespace, string $name, string $mode = 'replicated'): \Generator
    {
        $fullServiceName = "{$namespace}-{$name}";

        yield "Removing {$fullServiceName} Container" => sprintf(
            'docker service rm `docker service ls --format "{{.ID}}" --filter name=%s --filter mode=%s`',
            $fullServiceName,
            $mode,
        );
    }

    public static function transferAndLoadImage(string $namespace, string $imageName): \Generator
    {
        // Need to yield separate this custom command since it always execute on local
        yield 'Uploading Image To Server' => "upload /tmp/{$namespace}-{$imageName}.tar.gz:/tmp/{$namespace}-{$imageName}.tar.gz";

        yield <<<CMD
            docker load < /tmp/{$namespace}-{$imageName}.tar.gz
            rm -f /tmp/{$namespace}-{$imageName}.tar.gz
            docker image prune -a
        CMD;
    }

    public static function ensureServerArePrepared(string $namespace): \Generator
    {
        $node = yield 'Checking Docker Swarm' => 'docker node ls';
        if (empty($node)) {
            return false;
        }

        if (empty(yield 'Checking Traefik Proxy' => CommandHelper::isServiceRunning($namespace, 'mager_proxy'))) {
            return false;
        }

        return true;
    }
}
