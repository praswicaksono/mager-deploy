<?php
declare(strict_types=1);

namespace App\Component\Config;

interface ConfigInterface
{

    public static function fromFile(string $path): self;

    public function toFile(string $path): void;

    public function set(string $key, mixed $value = null): self;

    public function get(string $key, mixed $default = null): mixed;
}