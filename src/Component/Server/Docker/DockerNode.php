<?php
declare(strict_types=1);

namespace App\Component\Server\Docker;

final class DockerNode
{
    public function __construct(
        public readonly string $availability,
        public readonly string $engineVersion,
        public readonly string $hostname,
        public readonly string $id,
        public readonly bool $self,
        public readonly string $status,
        public readonly string $TlsStatus,
        public readonly ?string $managerStatus = null
    )
    {

    }

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
            managerStatus: $array['ManagerStatus']  ?? null
        );
    }
}