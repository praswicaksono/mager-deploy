<?php
declare(strict_types=1);

namespace App\Component\Config;

class ConfigFactory
{
    public static function create(): Config
    {
        $configPath = sprintf("%s/.mager/config.json", getenv('HOME'));

        return Json::fromFile($configPath);
    }
}