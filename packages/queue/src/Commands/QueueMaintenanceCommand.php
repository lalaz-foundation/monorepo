<?php

declare(strict_types=1);

namespace Lalaz\Queue\Commands;

use Lalaz\Queue\Drivers\AbstractDatabaseQueueDriver;
use Lalaz\Queue\QueueManager;

/**
 * Queue Maintenance Command
 *
 * Provides maintenance operations for the queue system:
 * - Release stuck jobs (jobs in "processing" state for too long)
 * - Cleanup old completed/failed jobs
 * - Display queue statistics
 *
 * @package elasticmind\lalaz-framework
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
class QueueMaintenanceCommand
{
    /**
     * Release stuck jobs back to pending status.
     *
     * @return int Number of jobs released
     */
    public static function releaseStuckJobs(): int
    {
        $queueManager = resolve(QueueManager::class);
        $driver = self::getDriver($queueManager);

        if (!($driver instanceof AbstractDatabaseQueueDriver)) {
            echo "Queue maintenance is only available for database drivers.\n";
            return 0;
        }

        $count = $driver->releaseStuckJobs();
        echo "Released {$count} stuck job(s).\n";

        return $count;
    }

    /**
     * Fail jobs that exceeded maximum retry attempts.
     *
     * @return int Number of jobs failed
     */
    public static function failExceededJobs(): int
    {
        $queueManager = resolve(QueueManager::class);
        $driver = self::getDriver($queueManager);

        if (!($driver instanceof AbstractDatabaseQueueDriver)) {
            echo "Queue maintenance is only available for database drivers.\n";
            return 0;
        }

        $count = $driver->failExceededJobs();
        echo "Failed {$count} job(s) that exceeded max attempts.\n";

        return $count;
    }

    /**
     * Cleanup old completed and failed jobs.
     *
     * @param int $olderThanDays Delete jobs older than this many days
     * @return int Number of jobs deleted
     */
    public static function cleanup(int $olderThanDays = 7): int
    {
        $queueManager = resolve(QueueManager::class);
        $driver = self::getDriver($queueManager);

        if (!($driver instanceof AbstractDatabaseQueueDriver)) {
            echo "Queue maintenance is only available for database drivers.\n";
            return 0;
        }

        $count = $driver->cleanup($olderThanDays);
        echo "Cleaned up {$count} old job(s) (older than {$olderThanDays} days).\n";

        return $count;
    }

    /**
     * Run all maintenance tasks.
     *
     * @param int $cleanupDays Days threshold for cleanup
     * @return array Statistics about maintenance operations
     */
    public static function runAll(int $cleanupDays = 7): array
    {
        echo "Running queue maintenance...\n\n";

        $stats = [
            'released' => self::releaseStuckJobs(),
            'failed' => self::failExceededJobs(),
            'cleaned' => self::cleanup($cleanupDays),
        ];

        echo "\nMaintenance completed.\n";

        return $stats;
    }

    /**
     * Get the driver from QueueManager via reflection.
     *
     * @param QueueManager $queueManager
     * @return mixed
     */
    private static function getDriver(QueueManager $queueManager): mixed
    {
        $reflection = new \ReflectionClass($queueManager);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);

        return $property->getValue($queueManager);
    }
}
