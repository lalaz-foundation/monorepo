<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for queue maintenance operations.
 *
 * Segregated interface following ISP - clients that only need
 * maintenance operations don't need to know about processing.
 */
interface QueueMaintenanceInterface
{
    /**
     * Delete old completed/failed jobs.
     *
     * @param int $olderThanDays Delete jobs older than this many days
     * @return int Number of jobs deleted
     */
    public function purgeOldJobs(int $olderThanDays = 7): int;

    /**
     * Delete all failed jobs.
     *
     * @param string|null $queue Specific queue name or null for all queues
     * @return int Number of failed jobs deleted
     */
    public function purgeFailedJobs(?string $queue = null): int;
}
