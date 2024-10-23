<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

final readonly class Option
{
    public function __construct(
        public ?float $limitCpu = null,
        public ?string $limitMemory = null,
    ) {}

    /**
     * @return array{'limit_cpu': float, 'limit_memory': string}
     */
    public function toArray(): array
    {
        return [
            'limit_cpu' => $this->limitCpu,
            'limit_memory' => $this->limitMemory,
        ];
    }
}
