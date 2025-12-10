<?php

declare(strict_types=1);

namespace Lalaz\Events\Drivers;

use Lalaz\Events\Contracts\EventDriverInterface;
use Lalaz\Events\Contracts\ListenerRegistryInterface;
use Lalaz\Events\Contracts\ListenerResolverInterface;
use Lalaz\Events\EventListener;
use Lalaz\Events\ListenerRegistry;
use Lalaz\Events\ListenerResolver;

/**
 * Synchronous event driver - executes listeners inline in the current process.
 *
 * This is the simplest driver, useful for:
 * - Development environments
 * - When you need guaranteed execution order
 * - Simple applications without async requirements
 *
 * Following SOLID:
 * - SRP: Listener storage delegated to ListenerRegistryInterface
 * - DIP: Depends on ListenerRegistryInterface and ListenerResolverInterface
 * - OCP: Behavior extensible via dependency injection
 *
 * @example
 * ```php
 * // With custom dependencies
 * $driver = new SyncDriver(
 *     registry: new ListenerRegistry(),
 *     resolver: ListenerResolver::from(fn($class) => $container->get($class))
 * );
 *
 * // Simple usage
 * $driver = new SyncDriver();
 * ```
 */
class SyncDriver implements EventDriverInterface
{
    private ListenerRegistryInterface $registry;
    private ListenerResolverInterface $resolver;

    /**
     * Create a new SyncDriver instance.
     *
     * @param ListenerRegistryInterface|null $registry Listener storage
     * @param ListenerResolverInterface|null $resolver Listener class resolver
     */
    public function __construct(
        ?ListenerRegistryInterface $registry = null,
        ?ListenerResolverInterface $resolver = null
    ) {
        $this->registry = $registry ?? new ListenerRegistry();
        $this->resolver = $resolver ?? new ListenerResolver();
    }

    /**
     * {@inheritdoc}
     */
    public function publish(string $event, mixed $data, array $options = []): void
    {
        $listeners = $this->registry->getWithMetadata($event);

        foreach ($listeners as $listenerEntry) {
            $listener = $listenerEntry['listener'];

            try {
                $this->invokeListener($listener, $data);
            } catch (\Throwable $e) {
                $this->handleError($event, $e, $options);
            }
        }
    }

    /**
     * Register a listener for an event.
     */
    public function addListener(
        string $event,
        callable|EventListener|string $listener,
        int $priority = 0
    ): void {
        $this->registry->add($event, $listener, $priority);
    }

    /**
     * Remove a listener from an event.
     */
    public function removeListener(string $event, callable|EventListener|string|null $listener = null): void
    {
        $this->registry->remove($event, $listener);
    }

    /**
     * Check if event has listeners.
     */
    public function hasListeners(string $event): bool
    {
        return $this->registry->has($event);
    }

    /**
     * Get all listeners for an event.
     *
     * @return array<callable|EventListener|string>
     */
    public function getListeners(string $event): array
    {
        return $this->registry->get($event);
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        return true; // Sync is always available
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'sync';
    }

    /**
     * Invoke a listener with the event data.
     */
    private function invokeListener(callable|EventListener|string $listener, mixed $data): void
    {
        if ($listener instanceof EventListener) {
            $listener->handle($data);
            return;
        }

        if (\is_string($listener) && class_exists($listener)) {
            $instance = $this->resolver->resolve($listener);
            if ($instance instanceof EventListener) {
                $instance->handle($data);
                return;
            }
            if (is_callable($instance)) {
                $instance($data);
                return;
            }
        }

        if (is_callable($listener)) {
            $listener($data);
        }
    }

    /**
     * Handle an error during listener execution.
     */
    private function handleError(string $event, \Throwable $e, array $options): void
    {
        $stopOnError = $options['stop_on_error'] ?? false;

        error_log("Error processing event '{$event}': " . $e->getMessage());

        if ($stopOnError) {
            throw $e;
        }
    }

    /**
     * Get the listener registry.
     */
    public function getRegistry(): ListenerRegistryInterface
    {
        return $this->registry;
    }

    /**
     * Get the listener resolver.
     */
    public function getResolver(): ListenerResolverInterface
    {
        return $this->resolver;
    }

    /**
     * Set the listener resolver.
     *
     * @deprecated Use constructor injection instead
     */
    public function setResolver(callable $resolver): self
    {
        $this->resolver = new ListenerResolver($resolver);
        return $this;
    }
}
