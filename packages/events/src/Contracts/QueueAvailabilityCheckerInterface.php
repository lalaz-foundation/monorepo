<?php

declare(strict_types=1);

namespace Lalaz\Events\Contracts;

/**
 * Interface for checking queue system availability.
 *
 * Following DIP - QueueDriver depends on this abstraction
 * instead of directly calling QueueManager static methods.
 */
interface QueueAvailabilityCheckerInterface
{
    /**
     * Check if the queue system is available and enabled.
     */
    public function isAvailable(): bool;
}
