<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use Lalaz\Queue\Tests\Common\FakeJob;
use Lalaz\Queue\Drivers\InMemoryQueueDriver;
use Lalaz\Queue\Contracts\JobInterface;
use Lalaz\Queue\Contracts\QueueDriverInterface;
use PHPUnit\Framework\Attributes\Test;

class InMemoryTestJob implements JobInterface
{
    public static array $processedPayloads = [];

    public static function reset(): void
    {
        self::$processedPayloads = [];
    }

    public function handle(array $payload): void
    {
        self::$processedPayloads[] = $payload;
    }
}

class InMemoryFailingJob implements JobInterface
{
    public static int $attempts = 0;

    public static function reset(): void
    {
        self::$attempts = 0;
    }

    public function handle(array $payload): void
    {
        self::$attempts++;
        throw new \RuntimeException('Job failed intentionally');
    }
}

/**
 * Unit tests for InMemoryQueueDriver class.
 *
 * @package lalaz/queue
 */
class InMemoryQueueDriverTest extends QueueUnitTestCase
{
    private InMemoryQueueDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new InMemoryQueueDriver();
        InMemoryTestJob::reset();
        InMemoryFailingJob::reset();
        FakeJob::reset();
    }

    // =========================================================================
    // Interface Implementation Tests
    // =========================================================================

    #[Test]
    public function it_implements_queue_driver_interface(): void
    {
        $this->assertInstanceOf(QueueDriverInterface::class, $this->driver);
    }

    // =========================================================================
    // Add Job Tests
    // =========================================================================

    #[Test]
    public function it_adds_a_job_to_the_queue(): void
    {
        $result = $this->driver->add(InMemoryTestJob::class, ['data' => 'test']);

        $this->assertTrue($result);
        $this->assertCount(1, $this->driver->all());
    }

    #[Test]
    public function it_adds_job_with_correct_default_values(): void
    {
        $this->driver->add(InMemoryTestJob::class, ['foo' => 'bar']);

        $jobs = $this->driver->all();
        $this->assertSame(InMemoryTestJob::class, $jobs[0]['task']);
        $this->assertSame(['foo' => 'bar'], $jobs[0]['payload']);
        $this->assertSame('default', $jobs[0]['queue']);
        $this->assertSame(5, $jobs[0]['priority']);
        $this->assertSame('pending', $jobs[0]['status']);
    }

    #[Test]
    public function it_adds_job_to_specific_queue(): void
    {
        $this->driver->add(InMemoryTestJob::class, [], 'emails');

        $jobs = $this->driver->all();
        $this->assertSame('emails', $jobs[0]['queue']);
    }

    #[Test]
    public function it_adds_job_with_custom_priority(): void
    {
        $this->driver->add(InMemoryTestJob::class, [], 'default', 10);

        $jobs = $this->driver->all();
        $this->assertSame(10, $jobs[0]['priority']);
    }

    #[Test]
    public function it_adds_delayed_job_with_correct_status(): void
    {
        $this->driver->add(InMemoryTestJob::class, [], 'default', 5, 60);

        $jobs = $this->driver->all();
        $this->assertSame('delayed', $jobs[0]['status']);
        $this->assertGreaterThan(time(), $jobs[0]['available_at']);
    }

    #[Test]
    public function it_stores_options_correctly(): void
    {
        $options = ['max_attempts' => 5, 'timeout' => 120];

        $this->driver->add(InMemoryTestJob::class, [], 'default', 5, null, $options);

        $jobs = $this->driver->all();
        $this->assertSame($options, $jobs[0]['options']);
    }

    #[Test]
    public function it_adds_multiple_jobs(): void
    {
        $this->driver->add(InMemoryTestJob::class, ['id' => 1]);
        $this->driver->add(InMemoryTestJob::class, ['id' => 2]);
        $this->driver->add(InMemoryTestJob::class, ['id' => 3]);

        $this->assertCount(3, $this->driver->all());
    }

    // =========================================================================
    // Get Stats Tests
    // =========================================================================

    #[Test]
    public function it_returns_correct_stats_for_empty_queue(): void
    {
        $stats = $this->driver->getStats();

        $this->assertSame(0, $stats['pending']);
        $this->assertSame(0, $stats['processing']);
        $this->assertSame(0, $stats['completed']);
        $this->assertSame(0, $stats['failed']);
        $this->assertSame(0, $stats['delayed']);
    }

    #[Test]
    public function it_counts_jobs_by_status_correctly(): void
    {
        $this->driver->add(InMemoryTestJob::class, []);
        $this->driver->add(InMemoryTestJob::class, []);
        $this->driver->add(InMemoryTestJob::class, [], 'default', 5, 60); // delayed

        $stats = $this->driver->getStats();

        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['delayed']);
    }

    #[Test]
    public function it_filters_stats_by_queue(): void
    {
        $this->driver->add(InMemoryTestJob::class, [], 'emails');
        $this->driver->add(InMemoryTestJob::class, [], 'emails');
        $this->driver->add(InMemoryTestJob::class, [], 'notifications');

        $emailStats = $this->driver->getStats('emails');
        $notificationStats = $this->driver->getStats('notifications');

        $this->assertSame(2, $emailStats['pending']);
        $this->assertSame(1, $notificationStats['pending']);
    }

    #[Test]
    public function it_returns_zero_stats_for_empty_queue(): void
    {
        $stats = $this->driver->getStats('nonexistent');

        $this->assertSame(0, $stats['pending']);
    }

    // =========================================================================
    // Get Failed Jobs Tests
    // =========================================================================

    #[Test]
    public function it_returns_empty_array_when_no_failed_jobs(): void
    {
        $this->driver->add(InMemoryTestJob::class, []);

        $failed = $this->driver->getFailedJobs();
        $this->assertEmpty($failed);
    }

    #[Test]
    public function it_respects_limit_when_getting_failed_jobs(): void
    {
        // Manually set up failed jobs since we can't easily process them
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
        ]);

        $failed = $this->driver->getFailedJobs(2);
        $this->assertCount(2, $failed);
    }

    #[Test]
    public function it_respects_offset_when_getting_failed_jobs(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default', 'id' => 1],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default', 'id' => 2],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default', 'id' => 3],
        ]);

        $failed = $this->driver->getFailedJobs(10, 1);
        $this->assertCount(2, $failed);
    }

    // =========================================================================
    // Get Failed Job Tests
    // =========================================================================

    #[Test]
    public function it_returns_null_for_non_existent_failed_job(): void
    {
        $job = $this->driver->getFailedJob(999);

        $this->assertNull($job);
    }

    #[Test]
    public function it_returns_failed_job_by_id(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            0 => ['task' => FakeJob::class, 'status' => 'pending', 'queue' => 'default'],
            1 => ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
        ]);

        $job = $this->driver->getFailedJob(1);

        $this->assertNotNull($job);
        $this->assertSame('failed', $job['status']);
    }

    #[Test]
    public function it_returns_null_for_non_failed_job_by_id(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            0 => ['task' => FakeJob::class, 'status' => 'pending', 'queue' => 'default'],
        ]);

        $job = $this->driver->getFailedJob(0);

        $this->assertNull($job);
    }

    // =========================================================================
    // Retry Failed Job Tests
    // =========================================================================

    #[Test]
    public function it_returns_false_when_retrying_non_existent_job(): void
    {
        $result = $this->driver->retryFailedJob(999);

        $this->assertFalse($result);
    }

    #[Test]
    public function it_retries_failed_job_successfully(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            0 => ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
        ]);

        $result = $this->driver->retryFailedJob(0);

        $this->assertTrue($result);
        $jobs = $this->driver->all();
        $this->assertSame('pending', $jobs[0]['status']);
    }

    #[Test]
    public function it_returns_false_when_retrying_non_failed_job(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            0 => ['task' => FakeJob::class, 'status' => 'pending', 'queue' => 'default'],
        ]);

        $result = $this->driver->retryFailedJob(0);

        $this->assertFalse($result);
    }

    // =========================================================================
    // Retry All Failed Jobs Tests
    // =========================================================================

    #[Test]
    public function it_returns_0_when_no_failed_jobs_to_retry(): void
    {
        $this->driver->add(InMemoryTestJob::class, []);

        $retried = $this->driver->retryAllFailedJobs();

        $this->assertSame(0, $retried);
    }

    #[Test]
    public function it_retries_all_failed_jobs(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
            ['task' => FakeJob::class, 'status' => 'pending', 'queue' => 'default'],
        ]);

        $retried = $this->driver->retryAllFailedJobs();

        $this->assertSame(2, $retried);

        $stats = $this->driver->getStats();
        $this->assertSame(3, $stats['pending']);
        $this->assertSame(0, $stats['failed']);
    }

    #[Test]
    public function it_retries_failed_jobs_filtered_by_queue(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'emails'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'notifications'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'emails'],
        ]);

        $retried = $this->driver->retryAllFailedJobs('emails');

        $this->assertSame(2, $retried);

        $emailStats = $this->driver->getStats('emails');
        $notificationStats = $this->driver->getStats('notifications');
        $this->assertSame(2, $emailStats['pending']);
        $this->assertSame(1, $notificationStats['failed']);
    }

    // =========================================================================
    // Purge Failed Jobs Tests
    // =========================================================================

    #[Test]
    public function it_returns_0_when_no_failed_jobs_to_purge(): void
    {
        $this->driver->add(InMemoryTestJob::class, []);

        $purged = $this->driver->purgeFailedJobs();

        $this->assertSame(0, $purged);
    }

    #[Test]
    public function it_purges_all_failed_jobs(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
            ['task' => FakeJob::class, 'status' => 'pending', 'queue' => 'default'],
        ]);

        $purged = $this->driver->purgeFailedJobs();

        $this->assertSame(2, $purged);
        $this->assertCount(1, $this->driver->all());
    }

    #[Test]
    public function it_purges_failed_jobs_filtered_by_queue(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'emails'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'notifications'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'emails'],
        ]);

        $purged = $this->driver->purgeFailedJobs('emails');

        $this->assertSame(2, $purged);
        $this->assertCount(1, $this->driver->all());
    }

    // =========================================================================
    // Purge Old Jobs Tests
    // =========================================================================

    #[Test]
    public function it_returns_0_for_purge_old_jobs(): void
    {
        $purged = $this->driver->purgeOldJobs(7);

        $this->assertSame(0, $purged);
    }

    // =========================================================================
    // Cleanup Tests
    // =========================================================================

    #[Test]
    public function it_removes_completed_and_failed_jobs(): void
    {
        $reflection = new \ReflectionClass($this->driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setValue($this->driver, [
            ['task' => FakeJob::class, 'status' => 'completed', 'queue' => 'default'],
            ['task' => FakeJob::class, 'status' => 'failed', 'queue' => 'default'],
            ['task' => FakeJob::class, 'status' => 'pending', 'queue' => 'default'],
        ]);

        $removed = $this->driver->cleanup();

        $this->assertSame(2, $removed);
        $this->assertCount(1, $this->driver->all());
    }

    #[Test]
    public function it_returns_0_when_nothing_to_cleanup(): void
    {
        $this->driver->add(InMemoryTestJob::class, []);
        $this->driver->add(InMemoryTestJob::class, []);

        $removed = $this->driver->cleanup();

        $this->assertSame(0, $removed);
        $this->assertCount(2, $this->driver->all());
    }

    // =========================================================================
    // Process Batch Tests
    // =========================================================================

    #[Test]
    public function it_returns_batch_statistics(): void
    {
        $stats = $this->driver->processBatch(10, null, 55);

        $this->assertArrayHasKey('processed', $stats);
        $this->assertArrayHasKey('successful', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('execution_time', $stats);
    }

    #[Test]
    public function it_returns_zero_stats_for_empty_queue_batch(): void
    {
        $stats = $this->driver->processBatch(10);

        $this->assertSame(0, $stats['processed']);
        $this->assertSame(0, $stats['successful']);
        $this->assertSame(0, $stats['failed']);
    }

    // =========================================================================
    // All Jobs Tests
    // =========================================================================

    #[Test]
    public function it_returns_all_jobs(): void
    {
        $this->driver->add(InMemoryTestJob::class, ['id' => 1]);
        $this->driver->add(InMemoryTestJob::class, ['id' => 2], 'emails');

        $all = $this->driver->all();

        $this->assertCount(2, $all);
        $this->assertSame(['id' => 1], $all[0]['payload']);
        $this->assertSame(['id' => 2], $all[1]['payload']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_jobs(): void
    {
        $all = $this->driver->all();

        $this->assertSame([], $all);
    }
}
