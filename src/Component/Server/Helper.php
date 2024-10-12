<?php

declare(strict_types=1);

namespace App\Component\Server;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Webmozart\Assert\Assert;

final class Helper
{
    /**
     * @param array<string|int, mixed> $args
     * @param string[]                 $cmd
     *
     * @return string[]
     */
    public static function buildOptions(string $options, array $args, array $cmd): array
    {
        foreach ($args as $key => $value) {
            $cmd[] = $options;
            if (is_int($key) && !empty($value)) {
                $cmd[] = "{$value}";
            } else {
                $cmd[] = "{$key}={$value}";
            }
        }

        return $cmd;
    }

    /**
     * @param array<string|int, mixed> $args
     *
     * @return string|bool|int|array<int|string, string|int|null>|null
     */
    public static function getArg(string $name, array $args, bool $required = true): string|bool|int|array|null
    {
        $value = $args[$name] ?? null;

        if ($required) {
            Assert::notEmpty($value, "{$name} must not be empty");
        }

        return $value;
    }

    /**
     * @template T
     *
     * @param callable(string): T $callback
     *
     * @return Collection<int, T>
     */
    public static function deserializeJsonList(string $json, callable $callback): Collection
    {
        $collection = new ArrayCollection();

        $items = explode(PHP_EOL, $json);
        foreach ($items as $item) {
            if (empty($item)) {
                continue;
            }
            $collection->add($callback($item));
        }

        return $collection;
    }
}
