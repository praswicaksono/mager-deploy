<?php
declare(strict_types=1);

namespace App\Component\Server;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Dotenv\Dotenv;
use Webmozart\Assert\Assert;

use function Amp\File\read;

final class Helper
{
    public static function buildOptions(string $options, array $args, array $cmd): array {
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

    public static function getArg(string $name, array $args, bool $required = true): string|bool|int|array|null
    {
        $value = $args[$name] ?? null;

        if ($required) {
            Assert::notEmpty($value);
        }

        return $value;
    }

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