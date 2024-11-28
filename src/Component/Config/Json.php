<?php

declare(strict_types=1);

namespace App\Component\Config;

use Adbar\Dot;
use App\Component\Config\Data\Server;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class Json implements Config
{
    /**
     * @var Dot<int|string, mixed>
     */
    private Dot $config;

    private string $path;

    public static function fromFile(string $path): self
    {
        $object = new self();

        $dir = str_replace('/config.json', '', $path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        touch($path);
        $json = file_get_contents($path);
        if (false === $json) {
            $json = '{}';
        }

        if (file_exists('./.mager/config.json')) {
            $json = file_get_contents('./.mager/config.json');
            if (false === $json) {
                $json = '{}';
            }
        }

        $object->config = \dot(json_decode($json, true));
        $object->path = $path;

        return $object;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key);
    }

    public function set(string $key, mixed $value = null): Config
    {
        $this->config->set($key, $value);

        return $this;
    }

    public function delete(string $key): Config
    {
        $this->config->delete($key);

        return $this;
    }

    public function save(): void
    {
        file_put_contents($this->path, json_encode($this->config->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function isLocal(string $namespace): bool
    {
        $isLocal = $this->get("{$namespace}.is_local") ?? null;

        if (null === $isLocal) {
            throw new \InvalidArgumentException("{$namespace} not exists, please run 'mager namespace:add <namespace>'");
        }

        return $isLocal;
    }

    public function isNotEmpty(): bool
    {
        return !$this->config->isEmpty();
    }

    public function isDebug(string $namespace = self::LOCAL_NAMESPACE): bool
    {
        return $this->get("{$namespace}.debug", false);
    }

    public function isSingleNode(string $namespace = Config::LOCAL_NAMESPACE): bool
    {
        return $this->get("{$namespace}.is_single_node", true) || ($this->getServers($namespace)->count() <= 1);
    }

    public function isMultiNode(string $namespace = self::LOCAL_NAMESPACE): bool
    {
        return !$this->isSingleNode($namespace);
    }

    public function getServers(string $namespace = self::LOCAL_NAMESPACE): Collection
    {
        $servers = $this->get("{$namespace}.servers", []);

        $servers = array_map(static function (array $server): Server {
            return new Server($server['ip'], $server['role'], $server['ssh_port'], $server['ssh_user'], $server['ssh_key_path'], str_replace('_', '.', $server['hostname']));
        }, $servers);

        return new ArrayCollection($servers);
    }

    public function getProxyDashboard(string $namespace = self::LOCAL_NAMESPACE): string
    {
        return $this->get("{$namespace}.proxy_dashboard");
    }

    public function getProxyUser(string $namespace = self::LOCAL_NAMESPACE): string
    {
        return $this->get("{$namespace}.proxy_user", 'admin');
    }

    public function getProxyPassword(string $namespace = self::LOCAL_NAMESPACE): string
    {
        return $this->get("{$namespace}.proxy_password");
    }

    public function all(): array
    {
        return $this->config->all();
    }
}
