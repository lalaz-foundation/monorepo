<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Events\Contracts\EventDispatcherInterface;
use Lalaz\Events\Contracts\EventDriverInterface;
use Lalaz\Events\Contracts\ListenerRegistryInterface;
use Lalaz\Events\Contracts\ListenerResolverInterface;
use Lalaz\Events\Drivers\QueueDriver;
use Lalaz\Events\Drivers\SyncDriver;

/**
 * EventHub - Main event dispatcher with driver-based architecture.
 *
 * Supports multiple drivers for different transport mechanisms:
 * - SyncDriver: Execute listeners inline (default for sync)
 * - QueueDriver: Dispatch via Queue package
 * - NullDriver: For testing
 * - Custom drivers: Redis Pub/Sub, Kafka, etc.
 *
 * Following SOLID:
 * - ISP: Implements EventDispatcherInterface (extends segregated interfaces)
 * - DIP: Dependencies can be injected via constructor
 * - OCP: Behavior extensible via driver injection
 *
 * @example
 * ```php
 * $hub = new EventHub();
 * $hub->register('user.created', function($data) {
 *     // Handle event
 * });
 * $hub->trigger('user.created', ['user_id' => 1]);
 * ```
 */
class EventHub implements EventDispatcherInterface
{
    private SyncDriver $syncDriver;
    private ?EventDriverInterface $asyncDriver = null;
    private bool $asyncEnabled = true;

    public function __construct(
        ?EventDriverInterface $asyncDriver = null,
        ?ListenerRegistryInterface $registry = null,
        ?ListenerResolverInterface $resolver = null
    ) {
        $this->syncDriver = new SyncDriver($registry, $resolver);
        $this->asyncDriver = $asyncDriver;
    }

    /**
     * Register a listener for an event.
     *
     * @param string $event The event name (uses $eventName for backwards compatibility)
     * @param callable|EventListener|string $listener The listener
     * @param int $priority Higher priority executes first (default: 0)
     */
    public function register(
        string $event,
        callable|EventListener|string $listener,
        int $priority = 0,
    ): void {
        $this->syncDriver->addListener($event, $listener, $priority);
    }

    /**
     * Remove a listener from an event.
     *
     * @param string $event The event name
     * @param callable|EventListener|string|null $listener Specific listener or null for all
     */
    public function forget(
        string $event,
        callable|EventListener|string|null $listener = null
    ): void {
        $this->syncDriver->removeListener($event, $listener);
    }

    /**
     * Trigger an event (async by default if driver is available).
     *
     * @param string $event The event name
     * @param mixed $data The event payload
     */
    public function trigger(
        string $event,
        mixed $data,
    ): void {
        if ($this->canDispatchAsync()) {
            $this->asyncDriver->publish($event, $data);
        } else {
            $this->triggerSync($event, $data);
        }
    }

    /**
     * Trigger listeners synchronously within the current process.
     */
    public function triggerSync(string $event, mixed $data): void
    {
        $this->syncDriver->publish($event, $data);
    }

    /**
     * Check if an event has any listeners.
     */
    public function hasListeners(string $event): bool
    {
        return $this->syncDriver->hasListeners($event);
    }

    /**
     * Get all listeners for an event.
     *
     * @return array<callable|EventListener|string>
     */
    public function getListeners(string $event): array
    {
        return $this->syncDriver->getListeners($event);
    }

    /**
     * Enable or disable async dispatching.
     */
    public function setAsyncEnabled(bool $enabled): void
    {
        $this->asyncEnabled = $enabled;
    }

    /**
     * Check if async is enabled.
     */
    public function isAsyncEnabled(): bool
    {
        return $this->asyncEnabled;
    }

    /**
     * Set the async driver.
     */
    public function setAsyncDriver(?EventDriverInterface $driver): void
    {
        $this->asyncDriver = $driver;
    }

    /**
     * Get the current async driver.
     */
    public function getAsyncDriver(): ?EventDriverInterface
    {
        return $this->asyncDriver;
    }

    /**
     * Get the sync driver.
     */
    public function getSyncDriver(): SyncDriver
    {
        return $this->syncDriver;
    }

    /**
     * Check if async dispatching is available.
     */
    private function canDispatchAsync(): bool
    {
        return $this->asyncEnabled
            && $this->asyncDriver !== null
            && $this->asyncDriver->isAvailable();
    }

    /**
     * Create an EventHub configured with Queue driver.
     */
    public static function withQueueDriver(
        string $queue = 'events',
        int $priority = 9
    ): self {
        return new self(new QueueDriver($queue, $priority));
    }

    /**
     * Create an EventHub for sync-only operation.
     */
    public static function syncOnly(): self
    {
        $hub = new self();
        $hub->setAsyncEnabled(false);
        return $hub;
    }
}
