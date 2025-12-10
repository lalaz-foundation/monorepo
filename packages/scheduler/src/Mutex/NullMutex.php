<?php

declare(strict_types=1);

namespace Lalaz\Scheduler\Mutex;

/**
 * NullMutex - No-op mutex for single server mode.
 *
 * This mutex always allows acquisition - used when mode='single'
 * and overlap prevention is not needed.
 *
 * @package lalaz/scheduler
 */
class NullMutex implements MutexInterface
{
    /**
     * Always returns true - no locking in single mode.
     *
     * @param string $name
     * @param int $expiresAt
     * @return bool
     */
    public function acquire(string $name, int $expiresAt): bool
    {
        return true;
    }

    /**
     * No-op release.
     *
     * @param string $name
     * @return void
     */
    public function release(string $name): void
    {
        // No-op in single mode
    }

    /**
     * Always returns false - nothing is ever locked in single mode.
     *
     * @param string $name
     * @return bool
     */
    public function exists(string $name): bool
    {
        return false;
    }
}
