<?php

declare(strict_types=1);

namespace App\Component\Config\Definition;

use Doctrine\Common\Collections\Collection;

final readonly class Proxy
{
    /**
     * @param Collection<int, ProxyPort> $ports
     */
    public function __construct(
        public string $rule,
        public Collection $ports,
    ) {}

    /**
     * @return array{'rule': string, 'ports': array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'ports' => $this->ports->map(function (ProxyPort $port): string {
                return $port->port;
            })->toArray(),
        ];
    }
}
