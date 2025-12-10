<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

/**
 * Factory interface for creating event jobs.
 *
 * Following DIP - QueueDriver depends on this abstraction
 * instead of concrete EventJob class.
 */
interface EventJobFactoryInterface
{
    /**
     * Create and configure a pending job dispatch.
     *
     * @param string $queue The queue name
     * @param int $priority Job priority
     * @param int|null $delay Delay in seconds
     * @return object A pending dispatch object with dispatch() method
     */
    public function create(string $queue, int $priority, ?int $delay = null): object;
}
