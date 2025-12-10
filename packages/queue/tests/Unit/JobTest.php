<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use Lalaz\Queue\Tests\Common\FakeJob;
use Lalaz\Queue\Tests\Common\DispatchableJob;
use Lalaz\Queue\Job;
use Lalaz\Queue\PendingDispatch;
use Lalaz\Queue\Contracts\JobDispatcherInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for abstract Job class.
 *
 * Tests the static dispatch methods and configuration.
 *
 * @package lalaz/queue
 */
class JobTest extends QueueUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeJob::reset();
        DispatchableJob::reset();
        DispatchableJob::setTestDispatcher(null);
        DispatchableJob::setDispatcherResolver(null);
        PendingDispatch::setTestDispatcher(null);
        PendingDispatch::setDispatcherResolver(null);
    }

    protected function tearDown(): void
    {
        DispatchableJob::setTestDispatcher(null);
        DispatchableJob::setDispatcherResolver(null);
        PendingDispatch::setTestDispatcher(null);
        PendingDispatch::setDispatcherResolver(null);
        parent::tearDown();
    }

    // =========================================================================
    // Dispatch Method Tests
    // =========================================================================

    #[Test]
    public function it_dispatches_job_using_test_dispatcher(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);

        $result = DispatchableJob::dispatch(['key' => 'value']);

        $this->assertTrue($result);
        $this->assertCount(1, $driver->addedJobs);
        $this->assertSame(DispatchableJob::class, $driver->addedJobs[0]['jobClass']);
        $this->assertSame(['key' => 'value'], $driver->addedJobs[0]['payload']);
    }

    #[Test]
    public function it_dispatches_job_with_empty_payload(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);

        $result = DispatchableJob::dispatch();

        $this->assertTrue($result);
        $this->assertSame([], $driver->addedJobs[0]['payload']);
    }

    #[Test]
    public function it_dispatches_to_default_queue(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);

        DispatchableJob::dispatch();

        $this->assertSame('default', $driver->addedJobs[0]['queue']);
    }

    #[Test]
    public function it_dispatches_with_default_priority(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);

        DispatchableJob::dispatch();

        $this->assertSame(5, $driver->addedJobs[0]['priority']);
    }

    #[Test]
    public function it_dispatches_with_job_options(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);

        DispatchableJob::dispatch();

        $options = $driver->addedJobs[0]['options'];
        $this->assertArrayHasKey('max_attempts', $options);
        $this->assertArrayHasKey('timeout', $options);
        $this->assertArrayHasKey('backoff_strategy', $options);
        $this->assertArrayHasKey('retry_delay', $options);
        $this->assertArrayHasKey('tags', $options);
    }

    // =========================================================================
    // Dispatch Sync Tests
    // =========================================================================

    #[Test]
    public function it_executes_job_synchronously(): void
    {
        $result = DispatchableJob::dispatchSync(['sync' => true]);

        $this->assertTrue($result);
        $this->assertTrue(DispatchableJob::wasHandled());
        $this->assertSame(['sync' => true], DispatchableJob::getHandledPayloads()[0]);
    }

    #[Test]
    public function it_handles_empty_payload_in_sync_dispatch(): void
    {
        $result = DispatchableJob::dispatchSync([]);

        $this->assertTrue($result);
        $this->assertSame([[]], DispatchableJob::getHandledPayloads());
    }

    #[Test]
    public function it_returns_false_when_sync_job_throws(): void
    {
        // Create a job class that throws
        $throwingJobClass = new class extends Job {
            public function handle(array $payload): void
            {
                throw new \RuntimeException('Intentional failure');
            }
        };

        // Suppress error_log output by redirecting to /dev/null
        $originalErrorLog = ini_get('error_log');
        ini_set('error_log', '/dev/null');
        
        try {
            $result = $throwingJobClass::dispatchSync([]);
            $this->assertFalse($result);
        } finally {
            ini_set('error_log', $originalErrorLog);
        }
    }

    // =========================================================================
    // OnQueue Method Tests
    // =========================================================================

    #[Test]
    public function it_creates_pending_dispatch_with_queue(): void
    {
        $pending = DispatchableJob::onQueue('emails');

        $this->assertInstanceOf(PendingDispatch::class, $pending);
    }

    #[Test]
    public function it_sets_queue_on_pending_dispatch(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);
        PendingDispatch::setTestDispatcher($driver);

        DispatchableJob::onQueue('notifications')->dispatch(['id' => 1]);

        $this->assertSame('notifications', $driver->addedJobs[0]['queue']);
    }

    // =========================================================================
    // WithPriority Method Tests
    // =========================================================================

    #[Test]
    public function it_creates_pending_dispatch_with_priority(): void
    {
        $pending = DispatchableJob::withPriority(10);

        $this->assertInstanceOf(PendingDispatch::class, $pending);
    }

    #[Test]
    public function it_sets_priority_on_pending_dispatch(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);
        PendingDispatch::setTestDispatcher($driver);

        DispatchableJob::withPriority(10)->dispatch(['id' => 1]);

        $this->assertSame(10, $driver->addedJobs[0]['priority']);
    }

    // =========================================================================
    // Later Method Tests
    // =========================================================================

    #[Test]
    public function it_creates_pending_dispatch_with_delay(): void
    {
        $pending = DispatchableJob::later(60);

        $this->assertInstanceOf(PendingDispatch::class, $pending);
    }

    #[Test]
    public function it_sets_delay_on_pending_dispatch(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);
        PendingDispatch::setTestDispatcher($driver);

        DispatchableJob::later(120)->dispatch(['id' => 1]);

        $this->assertSame(120, $driver->addedJobs[0]['delay']);
    }

    // =========================================================================
    // Test Dispatcher Tests
    // =========================================================================

    #[Test]
    public function it_uses_test_dispatcher_when_set(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);

        DispatchableJob::dispatch(['test' => true]);

        $this->assertCount(1, $driver->addedJobs);
    }

    #[Test]
    public function it_clears_test_dispatcher_when_set_to_null(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);
        DispatchableJob::setTestDispatcher(null);

        // Setting a resolver so dispatch doesn't fail
        $newDriver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($newDriver);

        DispatchableJob::dispatch();

        $this->assertCount(0, $driver->addedJobs);
        $this->assertCount(1, $newDriver->addedJobs);
    }

    // =========================================================================
    // Dispatcher Resolver Tests
    // =========================================================================

    #[Test]
    public function it_uses_dispatcher_resolver_when_set(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setDispatcherResolver(fn() => $driver);

        DispatchableJob::dispatch(['resolved' => true]);

        $this->assertCount(1, $driver->addedJobs);
    }

    #[Test]
    public function it_prefers_test_dispatcher_over_resolver(): void
    {
        $testDriver = $this->createMockDriver();
        $resolverDriver = $this->createMockDriver();

        DispatchableJob::setDispatcherResolver(fn() => $resolverDriver);
        DispatchableJob::setTestDispatcher($testDriver);

        DispatchableJob::dispatch();

        $this->assertCount(1, $testDriver->addedJobs);
        $this->assertCount(0, $resolverDriver->addedJobs);
    }

    // =========================================================================
    // Chained Dispatch Tests
    // =========================================================================

    #[Test]
    public function it_chains_queue_and_priority(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);
        PendingDispatch::setTestDispatcher($driver);

        DispatchableJob::onQueue('high-priority')
            ->priority(10)
            ->dispatch(['chained' => true]);

        $this->assertSame('high-priority', $driver->addedJobs[0]['queue']);
        $this->assertSame(10, $driver->addedJobs[0]['priority']);
    }

    #[Test]
    public function it_chains_priority_and_delay(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);
        PendingDispatch::setTestDispatcher($driver);

        DispatchableJob::withPriority(8)
            ->delay(300)
            ->dispatch(['chained' => true]);

        $this->assertSame(8, $driver->addedJobs[0]['priority']);
        $this->assertSame(300, $driver->addedJobs[0]['delay']);
    }

    #[Test]
    public function it_chains_delay_and_queue(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);
        PendingDispatch::setTestDispatcher($driver);

        DispatchableJob::later(600)
            ->onQueue('delayed-jobs')
            ->dispatch(['chained' => true]);

        $this->assertSame(600, $driver->addedJobs[0]['delay']);
        $this->assertSame('delayed-jobs', $driver->addedJobs[0]['queue']);
    }

    // =========================================================================
    // Multiple Dispatches Tests
    // =========================================================================

    #[Test]
    public function it_dispatches_multiple_jobs_independently(): void
    {
        $driver = $this->createMockDriver();
        DispatchableJob::setTestDispatcher($driver);
        PendingDispatch::setTestDispatcher($driver);

        DispatchableJob::dispatch(['id' => 1]);
        DispatchableJob::onQueue('emails')->dispatch(['id' => 2]);
        DispatchableJob::withPriority(10)->dispatch(['id' => 3]);

        $this->assertCount(3, $driver->addedJobs);
        $this->assertSame(['id' => 1], $driver->addedJobs[0]['payload']);
        $this->assertSame('emails', $driver->addedJobs[1]['queue']);
        $this->assertSame(10, $driver->addedJobs[2]['priority']);
    }
}
