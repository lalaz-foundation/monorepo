<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for processing jobs from the queue.
 *
 * Segregated interface following ISP - clients that only need to
 * process jobs don't need to know about adding or stats methods.
 */
interface JobProcessorInterface
{
    /**
     * Process jobs from the queue.
     *
     * @param string|null $queue Specific queue name or null for all
     */
    public function process(?string $queue = null): void;

    /**
     * Process a batch of jobs.
     *
     * @param int $batchSize Maximum number of jobs to process
     * @param string|null $queue Specific queue name or null for all
     * @param int $maxExecutionTime Maximum execution time in seconds
     * @return array Statistics about the batch processing
     */
    public function processBatch(
        int $batchSize = 10,
        ?string $queue = null,
        int $maxExecutionTime = 55,
    ): array;
}
