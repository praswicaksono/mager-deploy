<?php

declare(strict_types=1);

namespace App\Component\Config;

use Adbar\Dot;
use App\Component\Config\Data\Server;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use function Amp\File\createDirectory;
use function Amp\File\exists;
use function Amp\File\write;
use function Amp\File\read;

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
        if (! exists($dir)) {
            createDirectory($dir, 0755);
        }

        touch($path);
        $json = read($path);
        if (empty($json)) {
            $json = '{}';
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

    public function save(): void
    {
        write($this->path, json_encode($this->config->all(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function isLocal(): bool
    {
        return null !== $this->get(Config::LOCAL_NAMESPACE);
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
        return (bool) $this->get("{$namespace}.is_single_node", true);
    }

    public function isMultiNode(string $namespace = self::LOCAL_NAMESPACE): bool
    {
        return ! $this->isSingleNode($namespace);
    }

    public function getServers(string $namespace = self::LOCAL_NAMESPACE): Collection
    {
        $servers = $this->get("{$namespace}.servers", []);

        $servers = array_map(function (array $server): Server {
            return new Server($server['ip'], $server['role'], $server['ssh_port'], $server['ssh_user'], $server['ssh_key_path']);
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
}
