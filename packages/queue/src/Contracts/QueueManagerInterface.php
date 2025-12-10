<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for the queue manager.
 *
 * Combines segregated interfaces for full queue functionality.
 */
interface QueueManagerInterface extends
    JobDispatcherInterface,
    JobProcessorInterface,
    QueueStatsInterface,
    FailedJobsInterface,
    QueueMaintenanceInterface
{
    /**
     * Check if queue is enabled.
     */
    public static function isEnabled(): bool;
}
