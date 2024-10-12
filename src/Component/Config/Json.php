<?php

declare(strict_types=1);

namespace App\Component\Config;

use Adbar\Dot;

use function Amp\File\createDirectory;
use function Amp\File\exists;
use function Amp\File\write;
use function Amp\File\read;

final class Json implements Config
{
    private Dot $config;

    private string $path;

    public static function fromFile(string $path): self
    {
        $object = new self();

        $dir = str_replace('/config.json', '', $path);
        if (! exists($dir)) {
            createDirectory($dir, 0755);
        }

        touch($path);
        $json = read($path);
        if (empty($json)) {
            $json = '{}';
        }

        $object->config = \dot(json_decode($json, true));
        $object->path = $path;

        return $object;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key);
    }

    public function set(string $key, mixed $value = null): Config
    {
        $this->config->set($key, $value);

        return $this;
    }

    public function save(): void
    {
        write($this->path, $this->config->toJson());
    }
}
