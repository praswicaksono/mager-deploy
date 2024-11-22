<?php

declare(strict_types=1);

namespace App\Component\Config\Data;

final class Server
{
    public function __construct(
        public string $ip,
        public string $role,
        public ?int $port = null,
        public ?string $user = null,
        public ?string $keyPath = null,
        public ?string $hostname = null,
    ) {}

    /**
     * @return array<string, null|int|string>
     */
    public function toArray(): array
    {
        return [
            'ip' => $this->ip,
            'ssh_port' => $this->port,
            'ssh_user' => $this->user,
            'ssh_key_path' => $this->keyPath,
            'role' => $this->role,
            'hostname' => $this->hostname,
        ];
    }
}
