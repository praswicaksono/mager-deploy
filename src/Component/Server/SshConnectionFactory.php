<?php

declare(strict_types=1);

namespace App\Component\Server;

use Spatie\Ssh\Ssh;

final class SshConnectionFactory
{
    public static function create(
        string $user,
        string $domain,
        string $privateKey,
        bool $useMultiplex = false,
    ): Ssh {
        $privateKeyPath = "/tmp/{$user}.{$domain}";
        file_put_contents($privateKeyPath, $privateKey);

        $ssh = Ssh::create($user, $domain)
           ->disablePasswordAuthentication()
           ->usePrivateKey($privateKeyPath);

        if ($useMultiplex) {
            $ssh->useMultiplexing("/tmp/{$user}-{$domain}-%C");
        }

        return $ssh;
    }
}
