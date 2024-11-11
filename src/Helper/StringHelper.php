<?php

declare(strict_types=1);

namespace App\Helper;

final class StringHelper
{
    public static function extractConfigNameFromPath(string $namespace, string $path): string
    {
        $pathArr = explode('/', $path);
        $configName = str_replace('.', '_', end($pathArr));

        return "{$namespace}-{$configName}";
    }
}
