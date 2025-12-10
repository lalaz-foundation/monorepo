<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

use Lalaz\Events\EventListener;

/**
 * Interface for introspecting event listeners.
 *
 * Segregated interface following ISP - clients that only need to
 * query listener state don't need to know about other methods.
 */
interface EventIntrospectionInterface
{
    /**
     * Check if an event has any listeners.
     *
     * @param string $event The event name
     */
    public function hasListeners(string $event): bool;

    /**
     * Get all listeners for an event.
     *
     * @param string $event The event name
     * @return array<callable|EventListener|string>
     */
    public function getListeners(string $event): array;
}
