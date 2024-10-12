<?php

declare(strict_types=1);

namespace App\Component\Server\Task;

use App\Component\Server\FailedCommandException;
use App\Component\Server\TaskInterface;

/**
 * @template T
 * @implements TaskInterface<null>
 */
final class DebianInstallDocker implements TaskInterface
{
    public static function exec(array $args = []): array
    {
        return [
            'sudo apt-get install ca-certificates curl -y',
            'sudo install -m 0755 -d /etc/apt/keyrings',
            'sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc',
            'sudo chmod a+r /etc/apt/keyrings/docker.asc',
            'echo "deb [arch="$(dpkg --print-architecture)" signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \"$(. /etc/os-release && echo "$VERSION_CODENAME")\" stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null',
            'sudo apt update -y',
            'sudo apt install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin -y',
            'sudo groupadd docker',
            'sudo usermod -aG docker $USER',
        ];
    }

    public function result(int $statusCode, string $out, string $err): null
    {
        if (0 !== $statusCode) {
            FailedCommandException::throw($err, $statusCode);
        }

        return null;
    }
}
