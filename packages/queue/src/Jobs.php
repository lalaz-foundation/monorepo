<?php

declare(strict_types=1);

namespace Lalaz\Queue;

use Throwable;

/**
 * Jobs Facade
 *
 * Provides static access to job processing functionality.
 *
 * @package elasticmind\lalaz-framework
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
class Jobs
{
    /**
     * Run job processing.
     *
     * @param string|null $queue Process only jobs from this queue (null = all queues)
     * @return void
     */
    public static function run(?string $queue = null): void
    {
        try {
            $queueInfo = $queue ? " from queue '{$queue}'" : '';
            echo "Starting job execution{$queueInfo}...\n";

            resolve(QueueManager::class)->processJobs($queue);

            echo "Job execution finished.\n";
        } catch (Throwable $e) {
            echo 'An error occurred during job execution: ' .
                $e->getMessage() .
                "\n";
        }
    }

    /**
     * Process a batch of jobs.
     *
     * @param int $batchSize Number of jobs to process
     * @param string|null $queue Process only jobs from this queue (null = all queues)
     * @param int $maxExecutionTime Maximum execution time in seconds
     * @return array Statistics about the batch processing
     */
    public static function batch(
        int $batchSize = 10,
        ?string $queue = null,
        int $maxExecutionTime = 55,
    ): array {
        try {
            $queueInfo = $queue ? " from queue '{$queue}'" : '';
            echo "Processing batch{$queueInfo}...\n";

            $stats = resolve(QueueManager::class)->processBatch(
                $batchSize,
                $queue,
                $maxExecutionTime,
            );

            echo "Batch completed: {$stats['processed']} processed, " .
                "{$stats['successful']} successful, {$stats['failed']} failed\n";

            return $stats;
        } catch (Throwable $e) {
            echo 'An error occurred: ' . $e->getMessage() . "\n";
            return [
                'processed' => 0,
                'successful' => 0,
                'failed' => 0,
                'execution_time' => 0,
            ];
        }
    }
}
