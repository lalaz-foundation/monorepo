<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

use Lalaz\Events\EventListener;

/**
 * Interface for registering event listeners.
 *
 * Segregated interface following ISP - clients that only need to
 * register listeners don't need to know about triggering methods.
 */
interface EventRegistrarInterface
{
    /**
     * Register a listener for an event.
     *
     * @param string $event The event name
     * @param callable|EventListener|string $listener The listener
     * @param int $priority Higher priority executes first (default: 0)
     */
    public function register(
        string $event,
        callable|EventListener|string $listener,
        int $priority = 0
    ): void;

    /**
     * Remove a listener from an event.
     *
     * @param string $event The event name
     * @param callable|EventListener|string|null $listener Specific listener or null for all
     */
    public function forget(string $event, callable|EventListener|string|null $listener = null): void;
}
