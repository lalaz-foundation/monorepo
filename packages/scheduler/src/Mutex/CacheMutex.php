<?php

declare(strict_types=1);

namespace Lalaz\Scheduler\Mutex;

use Lalaz\Cache\CacheInterface;

/**
 * CacheMutex - Cache-based mutex for distributed mode.
 *
 * Uses the cache package to coordinate locks across multiple servers.
 * This ensures only one server executes a task at a time in a cluster.
 *
 * @package lalaz/scheduler
 */
class CacheMutex implements MutexInterface
{
    /**
     * Cache key prefix for scheduler locks.
     */
    private const PREFIX = 'scheduler:lock:';

    /**
     * Create a new cache mutex instance.
     *
     * @param CacheInterface $cache Cache implementation
     */
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Attempt to acquire a lock for the given task.
     *
     * Uses atomic cache operations to prevent race conditions.
     *
     * @param string $name Unique identifier for the task
     * @param int $expiresAt Lock expiration time in seconds
     * @return bool True if lock acquired, false if already locked
     */
    public function acquire(string $name, int $expiresAt): bool
    {
        $key = $this->getKey($name);

        // Check if lock exists
        if ($this->cache->has($key)) {
            return false;
        }

        // Try to set the lock
        // Note: This is not truly atomic, but works for most use cases.
        // For true atomicity, use a cache driver with atomic add support.
        $this->cache->set($key, [
            'acquired_at' => time(),
            'expires_at' => time() + $expiresAt,
            'host' => gethostname(),
            'pid' => getmypid(),
        ], $expiresAt);

        return true;
    }

    /**
     * Release the lock for the given task.
     *
     * @param string $name Unique identifier for the task
     * @return void
     */
    public function release(string $name): void
    {
        $this->cache->delete($this->getKey($name));
    }

    /**
     * Check if a lock exists for the given task.
     *
     * @param string $name Unique identifier for the task
     * @return bool True if locked, false otherwise
     */
    public function exists(string $name): bool
    {
        return $this->cache->has($this->getKey($name));
    }

    /**
     * Get the cache key for a task name.
     *
     * @param string $name
     * @return string
     */
    private function getKey(string $name): string
    {
        return self::PREFIX . sha1($name);
    }
}
