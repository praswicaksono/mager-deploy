<?php

declare(strict_types=1);

namespace App\Entity;

final readonly class DockerService
{
    public function __construct(
        public string $id,
        public string $image,
        public string $mode,
        public string $name,
        public string $ports,
        public string $replicas,
    ) {}

    public static function fromJsonString(string $json): DockerService
    {
        $array = json_decode($json, true);

        return new self(
            id: $array['ID'],
            image: $array['Image'],
            mode: $array['Mode'],
            name: $array['Name'],
            ports: $array['Ports'],
            replicas: $array['Replicas'],
        );
    }
}
