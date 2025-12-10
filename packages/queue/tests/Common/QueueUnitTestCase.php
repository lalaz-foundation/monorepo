<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Common;

use Lalaz\Testing\Unit\UnitTestCase;
use Lalaz\Queue\QueueManager;
use Lalaz\Queue\Drivers\InMemoryQueueDriver;

/**
 * Base test case for Queue package unit tests.
 *
 * Extends UnitTestCase from lalaz/testing to provide
 * common utilities plus queue-specific helpers like
 * job factories and queue assertions.
 *
 * @package lalaz/queue
 */
abstract class QueueUnitTestCase extends UnitTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        foreach ($this->getSetUpMethods() as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        foreach (array_reverse($this->getTearDownMethods()) as $method) {
            if (method_exists($this, $method)) {
                $this->{$method}();
            }
        }

        parent::tearDown();
    }

    /**
     * Get the list of setup methods to call.
     *
     * @return array<int, string>
     */
    protected function getSetUpMethods(): array
    {
        return [
            'setUpQueue',
            'setUpJobs',
        ];
    }

    /**
     * Get the list of teardown methods to call.
     *
     * @return array<int, string>
     */
    protected function getTearDownMethods(): array
    {
        return [
            'tearDownQueue',
            'tearDownJobs',
        ];
    }

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
    protected function createMockDriver(): MockQueueDriver
    {
        return new MockQueueDriver();
    }

    /**
     * Create a mock logger for testing.
     */
    protected function createMockLogger(): MockQueueLogger
    {
        return new MockQueueLogger();
    }

    /**
     * Create a QueueManager with optional driver.
     */
    protected function createQueueManager(?MockQueueDriver $driver = null): QueueManager
    {
        $driver = $driver ?? $this->createMockDriver();
        return new QueueManager($driver);
    }

    /**
     * Create an InMemoryQueueDriver.
     */
    protected function createInMemoryDriver(): InMemoryQueueDriver
    {
        return new InMemoryQueueDriver();
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
