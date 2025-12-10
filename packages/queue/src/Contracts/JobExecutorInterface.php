<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for executing jobs.
 *
 * Following DIP - drivers depend on this abstraction
 * instead of directly executing jobs.
 */
interface JobExecutorInterface
{
    /**
     * Execute a job.
     *
     * @param array $job The job data from the queue
     * @throws \Throwable If job execution fails
     */
    public function execute(array $job): void;

    /**
     * Execute a job synchronously (without queue).
     *
     * @param string $jobClass The job class name
     * @param array $payload The job payload
     * @return bool True if executed successfully
     */
    public function executeSync(string $jobClass, array $payload): bool;
}
