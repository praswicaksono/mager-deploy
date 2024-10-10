<?php
declare(strict_types=1);

namespace App\Component\Config;

use Adbar\Dot;

use function Amp\File\write;
use function Amp\File\read;

final class Json implements ConfigInterface
{
    private Dot $config;

    public static function fromFile(string $path): self
    {
        $object = new self();

        touch($path);
        $json = read($path);
        if (empty($json)) {
            $json = '{}';
        }

        $object->config = \dot(json_decode($json, true));

        return $object;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key);
    }

    public function set(string $key, mixed $value = null): ConfigInterface
    {
        $this->config->set($key, $value);

        return $this;
    }

    public function toFile(string $path): void
    {
        write($path, $this->config->toJson());
    }
}