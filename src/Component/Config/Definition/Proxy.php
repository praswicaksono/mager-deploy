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
        public string $host,
        public Collection $ports,
        public ?string $rule,
    ) {}

    /**
     * @return array{'rule': string, 'ports': array<int, string>}
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'rule' => $this->rule,
            'ports' => $this->ports->map(static function (ProxyPort $port): string {
                return $port->port;
            })->toArray(),
        ];
    }
}
