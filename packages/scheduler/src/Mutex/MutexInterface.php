<?php

declare(strict_types=1);

namespace Lalaz\Scheduler\Mutex;

/**
 * MutexInterface - Contract for scheduler mutex implementations.
 *
 * A mutex prevents overlapping execution of scheduled tasks.
 * In "single" mode, NullMutex is used (no-op).
 * In "distributed" mode, CacheMutex is used to coordinate across servers.
 *
 * @package lalaz/scheduler
 */
interface MutexInterface
{
    /**
     * Attempt to acquire a lock for the given task.
     *
     * @param string $name Unique identifier for the task
     * @param int $expiresAt Lock expiration time in seconds
     * @return bool True if lock acquired, false if already locked
     */
    public function acquire(string $name, int $expiresAt): bool;

    /**
     * Release the lock for the given task.
     *
     * @param string $name Unique identifier for the task
     * @return void
     */
    public function release(string $name): void;

    /**
     * Check if a lock exists for the given task.
     *
     * @param string $name Unique identifier for the task
     * @return bool True if locked, false otherwise
     */
    public function exists(string $name): bool;
}
