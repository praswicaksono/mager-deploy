<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final readonly class Build
{
    public function __construct(
        public string $context,
        public string $dockerfile,
        public ?string $target = null,
        public ?string $image = null,
    ) {}

    public function resolveImageNameTagFromEnv(): ?string
    {
        if (null === $this->image) {
            return null;
        }

        @[$name, $tag] = explode(':{', $this->image);
        if (empty($tag)) {
            return $this->image;
        }

        $tag = str_replace("{$name}:", '', $this->image);

        if (! str_starts_with($tag, '{') || ! str_ends_with($tag, '}')) {
            return $this->image;
        }

        [$env, $default] = explode(':-', $tag);

        $env = str_replace('{$', '', $env);
        $env = str_replace('}', '', $env);
        $value = getenv($env);

        if (false !== $value) {
            return "{$name}:{$value}";
        }

        if (empty($default)) {
            throw new \InvalidArgumentException("Env variable '{$env}' not found and default value is empty.");
        }

        $default = str_replace('}', '', $default);

        return "{$name}:{$default}";
    }
}
