<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

/**
 * Interface for drivers that support real-time subscriptions.
 *
 * Not all drivers support this (e.g., Queue-based don't).
 * Redis Pub/Sub, WebSockets, etc. would implement this.
 */
interface SubscribableDriverInterface extends EventDriverInterface
{
    /**
     * Subscribe to an event channel for real-time events.
     *
     * @param string $event The event name or pattern
     * @param callable $handler Handler function receiving event data
     */
    public function subscribe(string $event, callable $handler): void;

    /**
     * Unsubscribe from an event channel.
     *
     * @param string $event The event name or pattern
     */
    public function unsubscribe(string $event): void;

    /**
     * Start listening for events (blocking call for workers).
     */
    public function listen(): void;
}
