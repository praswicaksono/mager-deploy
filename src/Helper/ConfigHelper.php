<?php

declare(strict_types=1);

namespace App\Helper;

use Symfony\Component\Yaml\Yaml;

final class ConfigHelper
{
    public static function registerTLSCertificateLocally(string $domain): void
    {
        $path = getenv('HOME').'/.mager/dynamic.yaml';
        $arr = Yaml::parse(file_get_contents($path));

        $certs = $arr['tls']['certificates'] ?? [];

        foreach ($certs as $cert) {
            if (str_contains($cert['certFile'], $domain)) {
                return;
            }
        }

        $arr['tls']['certificates'][] = [
            'certFile' => "/var/certs/{$domain}.pem",
            'keyFile' => "/var/certs/{$domain}-key.pem",
        ];

        file_put_contents($path, Yaml::dump($arr));
    }
}
