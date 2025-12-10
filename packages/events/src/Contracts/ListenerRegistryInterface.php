<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

use Lalaz\Events\EventListener;

/**
 * Interface for managing event listener storage.
 *
 * Following SRP - this is a dedicated interface for
 * listener storage concerns, separate from event dispatching.
 */
interface ListenerRegistryInterface
{
    /**
     * Add a listener for an event.
     *
     * @param string $event The event name
     * @param callable|EventListener|string $listener The listener
     * @param int $priority Higher priority executes first
     */
    public function add(
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
    public function remove(string $event, callable|EventListener|string|null $listener = null): void;

    /**
     * Check if event has listeners.
     *
     * @param string $event The event name
     */
    public function has(string $event): bool;

    /**
     * Get all listeners for an event, sorted by priority.
     *
     * @param string $event The event name
     * @return array<callable|EventListener|string>
     */
    public function get(string $event): array;

    /**
     * Get all listeners with their metadata (priority, etc.).
     *
     * @param string $event The event name
     * @return array<array{listener: callable|EventListener|string, priority: int}>
     */
    public function getWithMetadata(string $event): array;

    /**
     * Clear all listeners for an event or all events.
     *
     * @param string|null $event Specific event or null for all
     */
    public function clear(?string $event = null): void;
}
