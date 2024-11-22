<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class DockerNode
{
    public function __construct(
        public string $availability,
        public string $engineVersion,
        public string $hostname,
        public string $id,
        public bool $self,
        public string $status,
        public string $TlsStatus,
        public ?string $managerStatus = null,
    ) {}

    public static function fromJsonString(string $json): DockerNode
    {
        $array = json_decode($json, true);

        return new self(
            availability: $array['Availability'],
            engineVersion: $array['EngineVersion'],
            hostname: $array['Hostname'],
            id: $array['ID'],
            self: $array['Self'],
            status: $array['Status'],
            TlsStatus: $array['TLSStatus'],
            managerStatus: $array['ManagerStatus'] ?? null,
        );
    }
}
