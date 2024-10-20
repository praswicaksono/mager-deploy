<?php
declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Definition\Build;
use Doctrine\Common\Collections\Collection;

final readonly class ServiceDefinition implements Definition
{
    public function __construct(
        public string $name,
        public Collection $services,
        public Build $build,
    ) {
    }
}