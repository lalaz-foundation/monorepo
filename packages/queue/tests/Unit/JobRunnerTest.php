<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use Lalaz\Queue\Tests\Common\MockQueueDriver;
use Lalaz\Queue\JobRunner;
use Lalaz\Queue\QueueManager;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for JobRunner class.
 *
 * Tests the simple runner that delegates to QueueManager.
 *
 * @package lalaz/queue
 */
class JobRunnerTest extends QueueUnitTestCase
{
    // =========================================================================
    // Constructor Tests
    // =========================================================================

    #[Test]
    public function it_accepts_queue_manager_in_constructor(): void
    {
        $manager = $this->createQueueManager();
        $runner = new JobRunner($manager);

        $this->assertInstanceOf(JobRunner::class, $runner);
    }

    // =========================================================================
    // Run Method Tests
    // =========================================================================

    #[Test]
    public function it_delegates_run_to_queue_manager(): void
    {
        $driver = $this->createMockDriver();
        $manager = $this->createQueueManager($driver);
        $runner = new JobRunner($manager);

        $runner->run();

        $this->assertSame(1, $driver->processCallCount);
    }

    #[Test]
    public function it_can_be_run_multiple_times(): void
    {
        $driver = $this->createMockDriver();
        $manager = $this->createQueueManager($driver);
        $runner = new JobRunner($manager);

        $runner->run();
        $runner->run();
        $runner->run();

        $this->assertSame(3, $driver->processCallCount);
    }

    #[Test]
    public function it_processes_all_queues_by_default(): void
    {
        $driver = $this->createMockDriver();
        $manager = $this->createQueueManager($driver);
        $runner = new JobRunner($manager);

        $runner->run();

        $this->assertNull($driver->lastProcessedQueue);
    }
}
