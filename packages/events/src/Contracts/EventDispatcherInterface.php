<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

use Lalaz\Events\EventListener;

/**
 * Interface for the main event dispatcher.
 *
 * Following ISP - this interface extends segregated interfaces,
 * allowing clients to depend only on what they need.
 */
interface EventDispatcherInterface extends
    EventPublisherInterface,
    EventRegistrarInterface,
    EventIntrospectionInterface
{
    // All methods are inherited from the segregated interfaces:
    //
    // From EventPublisherInterface:
    //   - trigger(string $event, mixed $data): void
    //   - triggerSync(string $event, mixed $data): void
    //
    // From EventRegistrarInterface:
    //   - register(string $event, callable|EventListener|string $listener, int $priority = 0): void
    //   - forget(string $event, callable|EventListener|string|null $listener = null): void
    //
    // From EventIntrospectionInterface:
    //   - hasListeners(string $event): bool
    //   - getListeners(string $event): array
}
