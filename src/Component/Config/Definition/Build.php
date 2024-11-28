<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final readonly class Build
{
    /**
     * @param array<string, string> $args
     */
    public function __construct(
        public string $image,
        public string $dockerfile,
        public array $args = [],
        public ?string $target = null,
    ) {}

    public function resolveImageNameTagFromEnv(): ?string
    {
        @[$name, $tag] = explode(':{', $this->image);
        if (empty($tag)) {
            return $this->image;
        }

        $tag = str_replace("{$name}:", '', $this->image);

        if (!str_starts_with($tag, '{') || !str_ends_with($tag, '}')) {
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

    /**
     * @return array<string, float|int|string>
     */
    public function resolveArgsValueFromEnv(): array
    {
        $args = $this->args;

        foreach ($this->args as $key => $value) {
            if (is_string($value) && str_starts_with('$', $value)) {
                $value = getenv(str_replace('$', '', $value));
            }
            $args[$key] = $value;
        }

        return $args;
    }
}
