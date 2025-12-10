<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

/**
 * Interface for event transport drivers.
 *
 * Drivers handle how events are published/dispatched to listeners.
 * Different drivers can use different transports (sync, queue, redis, etc.)
 */
interface EventDriverInterface
{
    /**
     * Publish an event to all registered listeners.
     *
     * @param string $event The event name
     * @param mixed $data The event payload
     * @param array<string, mixed> $options Driver-specific options
     */
    public function publish(string $event, mixed $data, array $options = []): void;

    /**
     * Check if the driver is available and properly configured.
     */
    public function isAvailable(): bool;

    /**
     * Get the driver name for identification.
     */
    public function getName(): string;
}
