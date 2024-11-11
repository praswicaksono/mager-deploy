<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Build;
use App\Component\Config\Definition\Config;
use App\Component\Config\Definition\Proxy;
use App\Component\Config\Trait\EnvResolver;
use App\Component\Config\Trait\VolumeParser;
use Doctrine\Common\Collections\Collection;

final readonly class AppDefinition implements Definition
{
    use EnvResolver;
    use VolumeParser;

    /**
     * @param Collection<int, Config>|null $config
     * @param string[]                     $volumes
     * @param string[]                     $env
     */
    public function __construct(
        public string $name,
        public Build $build,
        public ?string $cmd = null,
        public ?Proxy $proxy = null,
        public ?Collection $config = null,
        public array $volumes = [],
        public array $env = [],
    ) {}
}
