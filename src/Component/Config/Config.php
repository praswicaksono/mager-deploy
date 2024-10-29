<?php

declare(strict_types=1);

namespace App\Component\Config;

use App\Component\Config\Data\Server;
use Doctrine\Common\Collections\Collection;

interface Config
{
    public const string MAGER_GLOBAL_NETWORK = 'mager-global';

    public const string LOCAL_NAMESPACE = 'local';

    public static function fromFile(string $path): self;

    public function save(): void;

    public function set(string $key, mixed $value = null): self;

    public function delete(string $key): self;

    public function get(string $key, mixed $default = null): mixed;

    public function isLocal(string $namespace): bool;

    public function isNotEmpty(): bool;

    public function isDebug(string $namespace = self::LOCAL_NAMESPACE): bool;

    public function isSingleNode(string $namespace = self::LOCAL_NAMESPACE): bool;

    public function isMultiNode(string $namespace = self::LOCAL_NAMESPACE): bool;

    public function getProxyDashboard(string $namespace = self::LOCAL_NAMESPACE): string;

    public function getProxyUser(string $namespace = self::LOCAL_NAMESPACE): string;

    public function getProxyPassword(string $namespace = self::LOCAL_NAMESPACE): string;

    /**
     * @return array<string, string|array<string, mixed>>
     */
    public function all(): array;

    /**
     * @return Collection<int, Server>
     */
    public function getServers(string $namespace = self::LOCAL_NAMESPACE): Collection;
}
