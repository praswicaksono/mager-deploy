<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final class Config
{
    public string $configName;

    public string $destPath;

    public string $srcPath;

    public static function fromString(string $configLine): Config
    {
        $obj = new Config();
        [$src, $dest] = explode(':', $configLine, 2);

        $pathArr = explode('/', $src);
        $configName = str_replace('.', '_', end($pathArr));

        $obj->configName = $configName;
        $obj->destPath = $dest;
        $obj->srcPath = $src;

        return $obj;
    }
}
