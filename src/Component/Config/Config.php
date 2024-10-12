<?php

declare(strict_types=1);

namespace App\Component\Config;

interface Config
{
    public const string NAMESPACE = 'namespace';
    public const string IS_LOCAL = 'local';
    public const string DEBUG = 'debug';
    public const string PROXY_USER = 'proxy_user';
    public const string PROXY_PASSWORD = 'proxy_password';
    public const string PROXY_DASHBOARD = 'proxy_dashboard';

    public static function fromFile(string $path): self;

    public function save(): void;

    public function set(string $key, mixed $value = null): self;

    public function get(string $key, mixed $default = null): mixed;
}
