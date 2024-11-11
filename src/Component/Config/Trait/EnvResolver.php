<?php

declare(strict_types=1);

namespace App\Component\Config\Trait;

trait EnvResolver
{
    /**
     * @return array<string, string|int|float>
     */
    public function resolveEnvValue(): array
    {
        $envs = [];
        foreach ($this->env as $name => $value) {
            if (str_starts_with($value, '${') && str_ends_with($value, '}')) {
                $envName = str_replace('${', '', $value);
                $envName = str_replace('}', '', $envName);
                [$envName, $defaultValue] = explode(':-', $envName);

                $value = getenv($envName);
                if (false === $value && empty($defaultValue)) {
                    throw new \InvalidArgumentException("{$envName} not found in ENV and have no default value");
                }

                if (false === $value && '' !== $defaultValue) {
                    $envs[$name] = $defaultValue;
                    continue;
                }

                $envs[] = "{$name}={$value}";
                continue;
            }

            $envs[] = "{$name}={$value}";
        }

        return $envs;
    }
}
