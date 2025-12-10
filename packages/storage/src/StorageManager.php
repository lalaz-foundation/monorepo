<?php

declare(strict_types=1);

namespace Lalaz\Storage;

use Lalaz\Storage\Adapters\LocalStorageAdapter;
use Lalaz\Storage\Adapters\MemoryStorageAdapter;
use Lalaz\Storage\Contracts\StorageInterface;
use Lalaz\Storage\Exceptions\StorageException;

/**
 * StorageManager
 *
 * Manages storage drivers and provides factory methods for creating storage instances.
 *
 * @package lalaz/storage
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
class StorageManager
{
    /**
     * The default driver name.
     */
    protected string $defaultDriver = 'local';

    /**
     * Configuration for all disks.
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $disks = [];

    /**
     * Resolved driver instances.
     *
     * @var array<string, StorageInterface>
     */
    protected array $drivers = [];

    /**
     * Custom driver creators.
     *
     * @var array<string, callable>
     */
    protected array $customCreators = [];

    /**
     * Create a new StorageManager instance.
     *
     * @param array{
     *     default?: string,
     *     disks?: array<string, array<string, mixed>>
     * } $config Configuration options
     */
    public function __construct(array $config = [])
    {
        if (isset($config['default'])) {
            $this->defaultDriver = $config['default'];
        }

        if (isset($config['disks'])) {
            $this->disks = $config['disks'];
        }
    }

    /**
     * Get a storage driver instance.
     *
     * @param string|null $disk The disk name (null for default)
     * @return StorageInterface
     *
     * @throws StorageException If disk configuration is missing
     */
    public function disk(?string $disk = null): StorageInterface
    {
        $disk = $disk ?? $this->defaultDriver;

        if (isset($this->drivers[$disk])) {
            return $this->drivers[$disk];
        }

        return $this->drivers[$disk] = $this->resolve($disk);
    }

    /**
     * Get the default driver instance.
     */
    public function getDriver(): StorageInterface
    {
        return $this->disk();
    }

    /**
     * Get the default disk name.
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Set the default disk name.
     */
    public function setDefaultDriver(string $name): self
    {
        $this->defaultDriver = $name;
        return $this;
    }

    /**
     * Register a custom driver creator.
     *
     * @param string $driver The driver name
     * @param callable $callback The creator callback
     */
    public function extend(string $driver, callable $callback): self
    {
        $this->customCreators[$driver] = $callback;
        return $this;
    }

    /**
     * Add a disk configuration.
     *
     * @param string $name The disk name
     * @param array<string, mixed> $config The disk configuration
     */
    public function addDisk(string $name, array $config): self
    {
        $this->disks[$name] = $config;

        // Clear cached driver if it exists
        unset($this->drivers[$name]);

        return $this;
    }

    /**
     * Get all disk configurations.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getDisks(): array
    {
        return $this->disks;
    }

    /**
     * Clear all resolved driver instances.
     */
    public function purge(): void
    {
        $this->drivers = [];
    }

    /**
     * Resolve a disk instance.
     *
     * @throws StorageException If disk configuration is missing or driver is unknown
     */
    protected function resolve(string $disk): StorageInterface
    {
        if (!isset($this->disks[$disk])) {
            throw StorageException::missingConfiguration("disks.{$disk}");
        }

        $config = $this->disks[$disk];
        $driver = $config['driver'] ?? 'local';

        // Check for custom creator
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver, $config);
        }

        return match ($driver) {
            'local' => $this->createLocalDriver($config),
            'memory' => $this->createMemoryDriver($config),
            default => throw StorageException::unknownDriver($driver),
        };
    }

    /**
     * Create a local storage driver.
     *
     * @param array<string, mixed> $config
     */
    protected function createLocalDriver(array $config): LocalStorageAdapter
    {
        return new LocalStorageAdapter($config);
    }

    /**
     * Create a memory storage driver.
     *
     * @param array<string, mixed> $config
     */
    protected function createMemoryDriver(array $config): MemoryStorageAdapter
    {
        return new MemoryStorageAdapter($config);
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver The driver name
     * @param array<string, mixed> $config The driver configuration
     */
    protected function callCustomCreator(string $driver, array $config): StorageInterface
    {
        return $this->customCreators[$driver]($config);
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param array<int, mixed> $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->disk()->$method(...$parameters);
    }
}
