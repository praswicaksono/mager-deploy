<?php

declare(strict_types=1);

namespace App\Component\Server\Helper\Traefik;

use Webmozart\Assert\Assert;

final class Http
{
    public static function rule(string $name, string $rule): string
    {
        return "'traefik.http.routers.{$name}.rule={$rule}'";
    }

    public static function host(string $name, string $host): string
    {
        return "'traefik.http.routers.{$name}.rule=Host(`$host`)'";
    }

    public static function port(string $name, int $port): string
    {
        return "traefik.http.services.{$name}.loadbalancer.server.port={$port}";
    }

    public static function middleware(string $name, string $middleware): string
    {
        return "traefik.http.routers.{$name}.middlewares={$middleware}";
    }

    public static function service(string $name, string $service): string
    {
        return "traefik.http.routers.{$name}.service={$service}";
    }

    /**
     * @return array|string[]
     */
    public static function enable(?string $name = null, ?string $host = null, ?int $port = null): array
    {
        if (null === $name && null === $host && null === $port) {
            return [
                'traefik.enable=false',
            ];
        }

        Assert::notEmpty($name);
        Assert::notEmpty($host);
        Assert::notEmpty($port);

        return [
            'traefik.enable=true',
            self::host($name, $host),
            self::port($name, $port),
        ];
    }
}
