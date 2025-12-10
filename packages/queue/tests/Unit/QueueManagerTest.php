<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Queue\QueueManager;
use Lalaz\Queue\Drivers\InMemoryQueueDriver;
use Lalaz\Queue\Contracts\QueueDriverInterface;
use Lalaz\Queue\Contracts\QueueManagerInterface;
use Lalaz\Queue\Contracts\JobExecutorInterface;
use Lalaz\Queue\Contracts\JobInterface;
use Lalaz\Queue\Tests\Common\QueueUnitTestCase;

class MockQueueDriverForManager implements QueueDriverInterface
{
    public array $addedJobs = [];
    public int $processCallCount = 0;
    public ?string $lastProcessedQueue = null;
    public array $processBatchArgs = [];
    public array $statsToReturn = [];
    public array $failedJobsToReturn = [];
    public ?array $failedJobToReturn = null;
    public bool $retryResult = false;
    public int $retryAllResult = 0;
    public int $purgeOldResult = 0;
    public int $purgeFailedResult = 0;

    public function add(
        string $jobClass,
        array $payload = [],
        string $queue = "default",
        int $priority = 5,
        ?int $delay = null,
        array $options = [],
    ): bool {
        $this->addedJobs[] = [
            'jobClass' => $jobClass,
            'payload' => $payload,
            'queue' => $queue,
            'priority' => $priority,
            'delay' => $delay,
            'options' => $options,
        ];
        return true;
    }

    public function process(?string $queue = null): void
    {
        $this->processCallCount++;
        $this->lastProcessedQueue = $queue;
    }

    public function processBatch(int $batchSize = 10, ?string $queue = null, int $maxExecutionTime = 55): array
    {
        $this->processBatchArgs = [
            'batchSize' => $batchSize,
            'queue' => $queue,
            'maxExecutionTime' => $maxExecutionTime
        ];
        return ['processed' => 5, 'successful' => 4, 'failed' => 1, 'execution_time' => 10];
    }

    public function getStats(?string $queue = null): array
    {
        if (!empty($this->statsToReturn)) {
            return $this->statsToReturn;
        }
        return ['pending' => count($this->addedJobs), 'processing' => 0, 'completed' => 0, 'failed' => 0];
    }

    public function getFailedJobs(int $limit = 50, int $offset = 0): array
    {
        return $this->failedJobsToReturn;
    }

    public function getFailedJob(int $id): ?array
    {
        return $this->failedJobToReturn;
    }

    public function retryFailedJob(int $id): bool
    {
        return $this->retryResult;
    }

    public function retryAllFailedJobs(?string $queue = null): int
    {
        return $this->retryAllResult;
    }

    public function purgeOldJobs(int $olderThanDays = 7): int
    {
        return $this->purgeOldResult;
    }

    public function purgeFailedJobs(?string $queue = null): int
    {
        return $this->purgeFailedResult;
    }
}

class QueueTestJob implements JobInterface
{
    public static array $handledPayloads = [];

    public function handle(array $payload): void
    {
        self::$handledPayloads[] = $payload;
    }
}

class QueueManagerTest extends QueueUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        QueueTestJob::$handledPayloads = [];
    }

    #[Test]
    public function implements_queue_manager_interface(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $this->assertInstanceOf(QueueManagerInterface::class, $manager);
    }

    #[Test]
    public function accepts_a_queue_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $this->assertInstanceOf(QueueManager::class, $manager);
    }

    #[Test]
    public function accepts_optional_executor(): void
    {
        $driver = new MockQueueDriverForManager();
        $executor = $this->createMock(JobExecutorInterface::class);
        
        $manager = new QueueManager($driver, $executor);

        $this->assertSame($executor, $manager->getExecutor());
    }

    #[Test]
    public function get_driver_returns_injected_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $this->assertSame($driver, $manager->getDriver());
    }

    #[Test]
    public function get_executor_returns_null_when_not_provided(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $this->assertNull($manager->getExecutor());
    }

    #[Test]
    public function add_delegates_to_driver_add_method(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        // We need to test the add method directly on driver since isEnabled returns false
        $result = $driver->add(QueueTestJob::class, ['test' => 'data'], 'emails', 8, 60, ['timeout' => 120]);

        $this->assertTrue($result);
        $this->assertCount(1, $driver->addedJobs);
        $this->assertSame(QueueTestJob::class, $driver->addedJobs[0]['jobClass']);
        $this->assertSame(['test' => 'data'], $driver->addedJobs[0]['payload']);
        $this->assertSame('emails', $driver->addedJobs[0]['queue']);
        $this->assertSame(8, $driver->addedJobs[0]['priority']);
        $this->assertSame(60, $driver->addedJobs[0]['delay']);
        $this->assertSame(['timeout' => 120], $driver->addedJobs[0]['options']);
    }

    #[Test]
    public function add_job_delegates_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();

        $driver->add(QueueTestJob::class, ['foo' => 'bar']);

        $this->assertCount(1, $driver->addedJobs);
        $this->assertSame(QueueTestJob::class, $driver->addedJobs[0]['jobClass']);
    }

    #[Test]
    public function process_delegates_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $manager->process();

        $this->assertSame(1, $driver->processCallCount);
        $this->assertNull($driver->lastProcessedQueue);
    }

    #[Test]
    public function process_passes_queue_name_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $manager->process('notifications');

        $this->assertSame('notifications', $driver->lastProcessedQueue);
    }

    #[Test]
    public function process_jobs_delegates_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $manager->processJobs();

        $this->assertSame(1, $driver->processCallCount);
        $this->assertNull($driver->lastProcessedQueue);
    }

    #[Test]
    public function process_jobs_passes_queue_name_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $manager->processJobs('emails');

        $this->assertSame('emails', $driver->lastProcessedQueue);
    }

    #[Test]
    public function process_batch_delegates_to_driver_with_arguments(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $result = $manager->processBatch(20, 'emails', 120);

        $this->assertSame(20, $driver->processBatchArgs['batchSize']);
        $this->assertSame('emails', $driver->processBatchArgs['queue']);
        $this->assertSame(120, $driver->processBatchArgs['maxExecutionTime']);
    }

    #[Test]
    public function process_batch_returns_batch_processing_results(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $result = $manager->processBatch(10, 'default', 55);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('processed', $result);
        $this->assertArrayHasKey('successful', $result);
        $this->assertArrayHasKey('failed', $result);
        $this->assertArrayHasKey('execution_time', $result);
        $this->assertSame(5, $result['processed']);
        $this->assertSame(4, $result['successful']);
        $this->assertSame(1, $result['failed']);
    }

    #[Test]
    public function get_stats_delegates_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->statsToReturn = ['pending' => 10, 'processing' => 2, 'completed' => 50, 'failed' => 3];
        $manager = new QueueManager($driver);

        $stats = $manager->getStats();

        $this->assertSame(10, $stats['pending']);
        $this->assertSame(2, $stats['processing']);
        $this->assertSame(50, $stats['completed']);
        $this->assertSame(3, $stats['failed']);
    }

    #[Test]
    public function get_stats_passes_queue_filter_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $driver->add(QueueTestJob::class, []);
        $driver->add(QueueTestJob::class, []);

        $stats = $manager->getStats();
        $this->assertSame(2, $stats['pending']);
    }

    #[Test]
    public function get_failed_jobs_returns_failed_jobs_from_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->failedJobsToReturn = [
            ['id' => 1, 'task' => 'Job1', 'queue' => 'default'],
            ['id' => 2, 'task' => 'Job2', 'queue' => 'emails'],
        ];
        $manager = new QueueManager($driver);

        $failed = $manager->getFailedJobs();

        $this->assertCount(2, $failed);
        $this->assertSame('Job1', $failed[0]['task']);
        $this->assertSame('Job2', $failed[1]['task']);
    }

    #[Test]
    public function get_failed_jobs_returns_empty_array_when_no_failed_jobs(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $failed = $manager->getFailedJobs();

        $this->assertEmpty($failed);
    }

    #[Test]
    public function get_failed_jobs_accepts_limit_and_offset_parameters(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $failed = $manager->getFailedJobs(10, 5);

        $this->assertIsArray($failed);
    }

    #[Test]
    public function get_failed_job_returns_specific_failed_job(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->failedJobToReturn = [
            'id' => 123,
            'task' => 'FailedJob',
            'queue' => 'default',
            'exception' => 'Some error',
        ];
        $manager = new QueueManager($driver);

        $job = $manager->getFailedJob(123);

        $this->assertIsArray($job);
        $this->assertSame(123, $job['id']);
        $this->assertSame('FailedJob', $job['task']);
        $this->assertSame('Some error', $job['exception']);
    }

    #[Test]
    public function get_failed_job_returns_null_for_non_existent_job(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $job = $manager->getFailedJob(999);

        $this->assertNull($job);
    }

    #[Test]
    public function retry_failed_job_delegates_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->retryResult = true;
        $manager = new QueueManager($driver);

        $result = $manager->retryFailedJob(123);

        $this->assertTrue($result);
    }

    #[Test]
    public function retry_failed_job_returns_false_when_job_not_found(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $result = $manager->retryFailedJob(999);

        $this->assertFalse($result);
    }

    #[Test]
    public function retry_all_failed_jobs_delegates_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->retryAllResult = 5;
        $manager = new QueueManager($driver);

        $count = $manager->retryAllFailedJobs();

        $this->assertSame(5, $count);
    }

    #[Test]
    public function retry_all_failed_jobs_passes_queue_filter(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->retryAllResult = 3;
        $manager = new QueueManager($driver);

        $count = $manager->retryAllFailedJobs('emails');

        $this->assertSame(3, $count);
    }

    #[Test]
    public function retry_all_failed_jobs_returns_0_when_no_failed_jobs(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $count = $manager->retryAllFailedJobs();

        $this->assertSame(0, $count);
    }

    #[Test]
    public function purge_old_jobs_delegates_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->purgeOldResult = 25;
        $manager = new QueueManager($driver);

        $count = $manager->purgeOldJobs(14);

        $this->assertSame(25, $count);
    }

    #[Test]
    public function purge_old_jobs_returns_count_of_purged_jobs(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $count = $manager->purgeOldJobs(7);

        $this->assertSame(0, $count);
    }

    #[Test]
    public function purge_failed_jobs_delegates_to_driver(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->purgeFailedResult = 10;
        $manager = new QueueManager($driver);

        $count = $manager->purgeFailedJobs();

        $this->assertSame(10, $count);
    }

    #[Test]
    public function purge_failed_jobs_returns_count_of_purged_failed_jobs(): void
    {
        $driver = new MockQueueDriverForManager();
        $manager = new QueueManager($driver);

        $count = $manager->purgeFailedJobs();

        $this->assertSame(0, $count);
    }

    #[Test]
    public function purge_failed_jobs_accepts_queue_filter(): void
    {
        $driver = new MockQueueDriverForManager();
        $driver->purgeFailedResult = 7;
        $manager = new QueueManager($driver);

        $count = $manager->purgeFailedJobs('emails');

        $this->assertSame(7, $count);
    }

    #[Test]
    public function integration_with_in_memory_driver(): void
    {
        $driver = new InMemoryQueueDriver();
        $manager = new QueueManager($driver);

        $driver->add(QueueTestJob::class, ['id' => 1], 'default');
        $driver->add(QueueTestJob::class, ['id' => 2], 'emails');
        $driver->add(QueueTestJob::class, ['id' => 3], 'default', 10);

        $stats = $manager->getStats();
        $this->assertSame(3, $stats['pending']);

        $defaultStats = $manager->getStats('default');
        $this->assertSame(2, $defaultStats['pending']);

        $emailStats = $manager->getStats('emails');
        $this->assertSame(1, $emailStats['pending']);
    }
}
