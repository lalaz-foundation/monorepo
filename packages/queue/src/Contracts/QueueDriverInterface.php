<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Composite interface for queue drivers.
 *
 * Following ISP - this interface extends segregated interfaces.
 * Drivers can implement individual interfaces if they don't need all capabilities.
 */
interface QueueDriverInterface extends
    JobDispatcherInterface,
    JobProcessorInterface,
    QueueStatsInterface,
    FailedJobsInterface,
    QueueMaintenanceInterface
{
    // This composite interface combines all queue driver capabilities.
    // Drivers that need a subset of functionality can implement
    // the individual interfaces instead.
}
