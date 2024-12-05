<?php

declare(strict_types=1);

namespace App\Helper;

use App\Component\Config\Definition\Build;
use App\Component\TaskRunner\TaskBuilder\DockerCreateService;
use App\Component\TaskRunner\TaskBuilder\DockerImageBuild;

final class CommandHelper
{
    public static function generateTlsCertificateLocally(string $namespace, string $domain): \Generator
    {
        $path = getenv('HOME').'/.mager/certs';
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
            ->withCommand($domain)
        ;
    }

    /**
     * @return \Generator<int, string, string, bool>
     */
    public static function isServiceRunning(string $namespace, string $name): \Generator
    {
        $fullServiceName = "{$namespace}-{$name}";

        $ret = true;

        try {
            yield sprintf('docker service ps --format "{{.ID}}" %s', $fullServiceName);
        } catch (\Throwable) {
            $ret = false;
        }

        return $ret;
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

    public static function transferAndLoadImage(string $namespace, string $projectName): \Generator
    {
        // Need to yield separate this custom command since it always execute on local
        yield 'Uploading Image To Server' => "upload /tmp/{$namespace}-{$projectName}.tar.gz:/tmp/{$namespace}-{$projectName}.tar.gz";

        yield <<<CMD
            docker load < /tmp/{$namespace}-{$projectName}.tar.gz
            rm -f /tmp/{$namespace}-{$projectName}.tar.gz
            docker image prune -a
        CMD;
    }

    public static function ensureServerArePrepared(string $namespace): \Generator
    {
        $ret = true;

        try {
            yield 'docker node ls';
            if (!yield from CommandHelper::isServiceRunning($namespace, 'mager_proxy')) {
                $ret = false;
            }
        } catch (\Exception) {
            $ret = false;
        }

        return $ret;
    }

    public static function buildImage(string $tag, Build $buildDefinition, bool $push = false): \Generator
    {
        $build = DockerImageBuild::create($tag, '.')
            ->withFile($buildDefinition->dockerfile)
            ->withTarget($buildDefinition->target)
            ->withArgs($buildDefinition->resolveArgsValueFromEnv())
        ;
        if ($push) {
            $build = $build->withOutput('type=registry');
        }

        yield "Building {$tag}" => $build;
    }

    public static function dumpAndCompressImage(string $tag, string $namespace, string $projectName): \Generator
    {
        yield "Dump and Compress {$tag} Image" => "docker save {$tag} | gzip > /tmp/{$namespace}-{$projectName}.tar.gz";
    }

    /**
     * @return \Generator<int, string, string, string>
     */
    public static function getOSName(): \Generator
    {
        return trim(yield <<<'CMD'
            grep -w "ID" /etc/os-release | cut -d "=" -f 2 | tr -d '"'
        CMD);
    }

    /**
     * @return \Generator<int, string, string, int>
     */
    public static function getNumberOfRunningContainers(string $namespace, string $name): \Generator
    {
        return (int) trim(yield "docker service ps {$namespace}-{$name} | grep Running | wc -l");
    }
}
