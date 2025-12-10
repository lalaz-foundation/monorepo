<?php

declare(strict_types=1);

namespace Lalaz\Cache;

use Lalaz\Cache\Contracts\CacheStoreInterface;
use Lalaz\Cache\Stores\ApcuStore;
use Lalaz\Cache\Stores\ArrayStore;
use Lalaz\Cache\Stores\FileStore;
use Lalaz\Cache\Stores\NullStore;
use Lalaz\Cache\Stores\RedisStore;

final class CacheManager
{
    /** @var array<string, CacheStoreInterface> */
    private array $stores = [];

    private ?CacheStoreInterface $nullStore = null;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config = [])
    {
    }

    public function store(?string $name = null): CacheStoreInterface
    {
        $enabled = (bool) ($this->config['enabled'] ?? true);
        if (!$enabled) {
            return $this->nullStore ??= new NullStore();
        }

        $driverName = $name ?: (string) ($this->config['driver'] ?? 'array');

        if (isset($this->stores[$driverName])) {
            return $this->stores[$driverName];
        }

        $storesConfig = $this->config['stores'] ?? [];
        $storeConfig =
            is_array($storesConfig) && isset($storesConfig[$driverName])
                ? (array) $storesConfig[$driverName]
                : [];

        $driver = (string) ($storeConfig['driver'] ?? $driverName);
        $prefix = is_string($this->config['prefix'] ?? null)
            ? (string) $this->config['prefix']
            : 'lalaz_';

        $instance = $this->createStore($driver, $storeConfig, $prefix);
        $this->stores[$driverName] = $instance;
        return $instance;
    }

    private function createStore(
        string $driver,
        array $storeConfig,
        string $prefix,
    ): CacheStoreInterface {
        return match (strtolower($driver)) {
            'array' => new ArrayStore($prefix),
            'file' => $this->createFileStore($storeConfig, $prefix),
            'apcu' => new ApcuStore($prefix),
            'redis' => new RedisStore($storeConfig, $prefix),
            default => throw new CacheException(
                "Unsupported cache driver '{$driver}'",
            ),
        };
    }

    private function createFileStore(
        array $config,
        string $prefix,
    ): CacheStoreInterface {
        $path = $config['path'] ?? null;
        if (!is_string($path) || $path === '') {
            $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lalaz-cache';
        }

        return new FileStore($path, $prefix);
    }
}
