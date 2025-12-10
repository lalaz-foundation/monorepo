<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for managing failed jobs.
 *
 * Segregated interface following ISP - clients that only need
 * failed job management don't need to know about processing.
 */
interface FailedJobsInterface
{
    /**
     * List failed jobs.
     *
     * @param int $limit Maximum number of failed jobs to return
     * @param int $offset Offset for pagination
     * @return array List of failed jobs
     */
    public function getFailedJobs(int $limit = 50, int $offset = 0): array;

    /**
     * Get a specific failed job by ID.
     *
     * @param int $id Failed job ID
     * @return array|null Failed job data or null if not found
     */
    public function getFailedJob(int $id): ?array;

    /**
     * Retry a specific failed job.
     *
     * @param int $id Failed job ID
     * @return bool True if job was successfully retried
     */
    public function retryFailedJob(int $id): bool;

    /**
     * Retry all failed jobs, optionally filtered by queue.
     *
     * @param string|null $queue Specific queue name or null for all queues
     * @return int Number of jobs retried
     */
    public function retryAllFailedJobs(?string $queue = null): int;
}
