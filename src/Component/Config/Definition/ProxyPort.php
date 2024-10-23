<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final readonly class ProxyPort
{
    public function __construct(
        public string $port,
    ) {}

    public function getPort(): int
    {
        [$port] = explode('/', $this->port);

        return (int) $port;
    }

    public function getProtocol(): string
    {
        [, $protocol] = explode('/', $this->port);

        return $protocol;
    }
}
