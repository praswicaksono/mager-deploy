<?php
declare(strict_types=1);

namespace App\Helper;

final class Encryption
{
    public static function htpasswd(string $password, ?string $salt = null): string
    {
        if (is_null($salt)) $salt = str_shuffle('./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz');
        $salt = substr($salt, 0, 8);

        $len = strlen($password);
        $text = $password.'$apr1$'.$salt;
        $bin = pack("H32", md5($password.$salt.$password));

        for ($i = $len; $i > 0; $i -= 16) $text .= substr($bin, 0, min(16, $i));
        for ($i = $len; $i > 0; $i >>= 1) $text .= ($i & 1) ? chr(0) : $password[0];

        $bin = pack("H32", md5($text));

        for ($i = 0; $i < 1000; $i++)
        {
            $new = ($i & 1) ? $password : $bin;
            if ($i % 3) $new .= $salt;
            if ($i % 7) $new .= $password;
            $new .= ($i & 1) ? $bin : $password;
            $bin = pack("H32", md5($new));
        }

        $tmp = '';
        for ($i = 0; $i < 5; $i++)
        {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) $j = 5;
            $tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
        }

        $tmp = chr(0).chr(0).$bin[11].$tmp;
        $tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
            "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
            "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");

        return '$apr1$'.$salt.'$'.$tmp;
    }
}