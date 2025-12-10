<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Common;

use Lalaz\Testing\Integration\IntegrationTestCase;
use Lalaz\Queue\Tests\Common\FakeJob;
use Lalaz\Queue\Tests\Common\FailingJob;
use Lalaz\Queue\Tests\Common\RecordingJob;
use Lalaz\Queue\Tests\Common\MockQueueDriver;
use Lalaz\Queue\Tests\Common\MockQueueLogger;

/**
 * Base test case for Queue package integration tests.
 *
 * Extends IntegrationTestCase from lalaz/testing to provide
 * container bootstrapping plus queue-specific helpers.
 *
 * @package lalaz/queue
 */
abstract class QueueIntegrationTestCase extends IntegrationTestCase
{
    /**
     * Default queue name for testing.
     */
    protected const DEFAULT_QUEUE = 'default';

    /**
     * Default job timeout in seconds.
     */
    protected const JOB_TIMEOUT = 300;

    /**
     * Default max attempts.
     */
    protected const MAX_ATTEMPTS = 3;

    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create a fake job for testing.
     */
    protected function fakeJob(): FakeJob
    {
        FakeJob::reset();
        return new FakeJob();
    }

    /**
     * Create a failing job for testing error handling.
     */
    protected function failingJob(string $errorMessage = 'Job failed'): FailingJob
    {
        return new FailingJob($errorMessage);
    }

    /**
     * Create a recording job that tracks execution.
     */
    protected function recordingJob(): RecordingJob
    {
        return new RecordingJob();
    }

    /**
     * Create a mock queue driver for testing.
     */
    protected function mockDriver(): MockQueueDriver
    {
        return new MockQueueDriver();
    }

    /**
     * Create a mock logger for testing.
     */
    protected function mockLogger(): MockQueueLogger
    {
        return new MockQueueLogger();
    }

    // =========================================================================
    // Queue Helpers
    // =========================================================================

    /**
     * Get default queue configuration for tests.
     *
     * @return array<string, mixed>
     */
    protected function getQueueConfig(): array
    {
        return [
            'enabled' => true,
            'default' => static::DEFAULT_QUEUE,
            'job_timeout' => static::JOB_TIMEOUT,
            'max_attempts' => static::MAX_ATTEMPTS,
            'driver' => 'memory',
        ];
    }

    /**
     * Get database queue configuration for tests.
     *
     * @return array<string, mixed>
     */
    protected function getDatabaseQueueConfig(): array
    {
        return [
            'enabled' => true,
            'default' => static::DEFAULT_QUEUE,
            'job_timeout' => static::JOB_TIMEOUT,
            'max_attempts' => static::MAX_ATTEMPTS,
            'driver' => 'database',
            'database' => [
                'table' => 'jobs',
            ],
        ];
    }

    // =========================================================================
    // Queue Assertions
    // =========================================================================

    /**
     * Assert that a job was added to the driver.
     */
    protected function assertJobAdded(MockQueueDriver $driver, string $jobClass, string $message = ''): void
    {
        $found = false;
        foreach ($driver->addedJobs as $job) {
            if ($job['jobClass'] === $jobClass) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, $message ?: "Job {$jobClass} was not added to the driver");
    }

    /**
     * Assert that no job was added to the driver.
     */
    protected function assertNoJobAdded(MockQueueDriver $driver, string $message = ''): void
    {
        $this->assertEmpty($driver->addedJobs, $message ?: "Expected no jobs to be added");
    }

    /**
     * Assert job count in mock driver.
     */
    protected function assertJobCount(MockQueueDriver $driver, int $expectedCount, string $message = ''): void
    {
        $this->assertCount(
            $expectedCount,
            $driver->addedJobs,
            $message ?: "Expected {$expectedCount} jobs, got " . count($driver->addedJobs)
        );
    }

    /**
     * Assert that a job was added to a specific queue.
     */
    protected function assertJobOnQueue(MockQueueDriver $driver, string $queue, string $message = ''): void
    {
        $found = false;
        foreach ($driver->addedJobs as $job) {
            if ($job['queue'] === $queue) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, $message ?: "No job was added to queue '{$queue}'");
    }

    /**
     * Assert that a job has a specific priority.
     */
    protected function assertJobPriority(MockQueueDriver $driver, int $priority, string $message = ''): void
    {
        $found = false;
        foreach ($driver->addedJobs as $job) {
            if ($job['priority'] === $priority) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, $message ?: "No job was added with priority {$priority}");
    }

    /**
     * Assert that a job has a specific delay.
     */
    protected function assertJobDelay(MockQueueDriver $driver, int $delay, string $message = ''): void
    {
        $found = false;
        foreach ($driver->addedJobs as $job) {
            if ($job['delay'] === $delay) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, $message ?: "No job was added with delay {$delay}");
    }

    // =========================================================================
    // Logger Assertions
    // =========================================================================

    /**
     * Assert mock logger logged a message at the specified level.
     */
    protected function assertLoggedAt(MockQueueLogger $logger, string $level, string $messageContains = ''): void
    {
        $logs = $logger->getLogsByLevel($level);
        $this->assertNotEmpty($logs, "No logs found at level '{$level}'");

        if ($messageContains !== '') {
            $found = false;
            foreach ($logs as $log) {
                if (str_contains($log['message'], $messageContains)) {
                    $found = true;
                    break;
                }
            }
            $this->assertTrue($found, "No log message containing '{$messageContains}' found at level '{$level}'");
        }
    }

    /**
     * Assert that error was logged.
     */
    protected function assertErrorLogged(MockQueueLogger $logger, string $messageContains = ''): void
    {
        $this->assertLoggedAt($logger, 'error', $messageContains);
    }

    /**
     * Assert that warning was logged.
     */
    protected function assertWarningLogged(MockQueueLogger $logger, string $messageContains = ''): void
    {
        $this->assertLoggedAt($logger, 'warning', $messageContains);
    }

    /**
     * Assert that info was logged.
     */
    protected function assertInfoLogged(MockQueueLogger $logger, string $messageContains = ''): void
    {
        $this->assertLoggedAt($logger, 'info', $messageContains);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create job data array for testing.
     */
    protected function createJobData(array $overrides = []): array
    {
        return array_merge([
            'id' => 1,
            'queue' => 'default',
            'task' => FakeJob::class,
            'payload' => '{}',
            'status' => 'pending',
            'priority' => 5,
            'attempts' => 0,
            'max_attempts' => 3,
            'available_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], $overrides);
    }

    /**
     * Create payload JSON for testing.
     */
    protected function createPayloadJson(array $data): string
    {
        return json_encode($data);
    }
}
