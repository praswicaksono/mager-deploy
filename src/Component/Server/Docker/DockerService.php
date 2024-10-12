<?php

namespace App\Component\Server\Docker;

final class DockerService
{
    public function __construct(
        public readonly string $id,
        public readonly string $image,
        public readonly string $mode,
        public readonly string $name,
        public readonly string $ports,
        public readonly string $replicas,
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
