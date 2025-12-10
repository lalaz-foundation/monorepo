<?php

declare(strict_types=1);

namespace Lalaz\Events;

use Lalaz\Events\Contracts\EventDispatcherInterface;
use Lalaz\Events\Contracts\EventDriverInterface;
use Lalaz\Events\Contracts\ListenerRegistryInterface;
use Lalaz\Events\Contracts\ListenerResolverInterface;
use Lalaz\Events\Drivers\NullDriver;
use Lalaz\Events\Drivers\QueueDriver;
use Lalaz\Events\Drivers\SyncDriver;

/**
 * Event Manager - Central coordinator for the event system.
 *
 * Manages event listeners and coordinates between sync and async drivers.
 * The SyncDriver handles listener storage and synchronous execution,
 * while the async driver (Queue, Redis, etc.) handles async publishing.
 *
 * Following SOLID:
 * - ISP: Implements EventDispatcherInterface (extends segregated interfaces)
 * - DIP: Dependencies can be injected via constructor
 * - OCP: Behavior extensible via driver injection
 */
class EventManager implements EventDispatcherInterface
{
    private SyncDriver $syncDriver;
    private ?EventDriverInterface $asyncDriver;
    private bool $asyncEnabled;

    /**
     * @param EventDriverInterface|null $asyncDriver Driver for async events (null = sync only)
     * @param bool $asyncEnabled Whether async is enabled by default
     * @param ListenerRegistryInterface|null $registry Listener storage
     * @param ListenerResolverInterface|null $resolver Listener class resolver
     */
    public function __construct(
        ?EventDriverInterface $asyncDriver = null,
        bool $asyncEnabled = true,
        ?ListenerRegistryInterface $registry = null,
        ?ListenerResolverInterface $resolver = null
    ) {
        $this->syncDriver = new SyncDriver($registry, $resolver);
        $this->asyncDriver = $asyncDriver;
        $this->asyncEnabled = $asyncEnabled;
    }

    /**
     * {@inheritdoc}
     */
    public function register(
        string $event,
        callable|EventListener|string $listener,
        int $priority = 0
    ): void {
        $this->syncDriver->addListener($event, $listener, $priority);
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $event, callable|EventListener|string|null $listener = null): void
    {
        $this->syncDriver->removeListener($event, $listener);
    }

    /**
     * {@inheritdoc}
     *
     * Triggers async if enabled and driver is available, otherwise falls back to sync.
     */
    public function trigger(string $event, mixed $data): void
    {
        if ($this->shouldDispatchAsync()) {
            $this->asyncDriver->publish($event, $data);
        } else {
            $this->triggerSync($event, $data);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function triggerSync(string $event, mixed $data): void
    {
        $this->syncDriver->publish($event, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function hasListeners(string $event): bool
    {
        return $this->syncDriver->hasListeners($event);
    }

    /**
     * {@inheritdoc}
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
     * Get the sync driver (for direct access if needed).
     */
    public function getSyncDriver(): SyncDriver
    {
        return $this->syncDriver;
    }

    /**
     * Check if we should dispatch async.
     */
    private function shouldDispatchAsync(): bool
    {
        return $this->asyncEnabled
            && $this->asyncDriver !== null
            && $this->asyncDriver->isAvailable();
    }

    /**
     * Create a manager with Queue driver.
     */
    public static function withQueueDriver(
        string $queue = 'events',
        int $priority = 9
    ): self {
        return new self(new QueueDriver($queue, $priority));
    }

    /**
     * Create a manager for sync-only operation.
     */
    public static function syncOnly(): self
    {
        return new self(null, false);
    }

    /**
     * Create a manager with null driver (for testing).
     */
    public static function forTesting(bool $recordEvents = true): self
    {
        return new self(new NullDriver($recordEvents), true);
    }
}
