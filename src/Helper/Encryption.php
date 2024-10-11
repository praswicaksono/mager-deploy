<?php
declare(strict_types=1);

namespace App\Helper;

final class Encryption
{
    public static function Htpasswd(string $password): string
    {
        $hash = base64_encode(sha1($password, true));
        return '{SHA}' . $hash;
    }
}