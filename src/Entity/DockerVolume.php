<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class DockerVolume
{
    public function __construct(
        public string $availability,
        public string $driver,
        public string $group,
        public string $name,
        public string $scope,
        public string $labels,
        public string $mountpoint,
        public string $links,
        public string $size,
        public string $status,
    ) {}

    public static function fromJsonString(string $json): self
    {
        $array = json_decode($json, true);

        return new self(
            availability: $array['Availability'],
            driver: $array['Driver'],
            group: $array['Group'],
            name: $array['Name'],
            scope: $array['Scope'],
            labels: $array['Labels'],
            mountpoint: $array['Mountpoint'],
            links: $array['Links'],
            size: $array['Size'],
            status: $array['Status'],
        );
    }
}
