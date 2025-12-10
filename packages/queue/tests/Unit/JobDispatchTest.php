<?php

declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Contracts\JobDispatcherInterface;
use Lalaz\Queue\Contracts\JobInterface;
use Lalaz\Queue\Job;
use Lalaz\Queue\PendingDispatch;
use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use PHPUnit\Framework\Attributes\Test;

class JobDispatchTest extends QueueUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Reset static state
        ConcreteTestJob::setTestDispatcher(null);
        ConcreteTestJob::setDispatcherResolver(null);
        PendingDispatch::setTestDispatcher(null);
        PendingDispatch::setDispatcherResolver(null);
    }

    protected function tearDown(): void
    {
        // Clean up static state
        ConcreteTestJob::setTestDispatcher(null);
        ConcreteTestJob::setDispatcherResolver(null);
        PendingDispatch::setTestDispatcher(null);
        PendingDispatch::setDispatcherResolver(null);
        parent::tearDown();
    }

    #[Test]
    public function job_dispatch_uses_test_dispatcher_when_set(): void
    {
        $dispatchedJobs = [];

        $dispatcher = new class($dispatchedJobs) implements JobDispatcherInterface {
            public function __construct(private array &$jobs) {}

            public function add(
                string $jobClass,
                array $payload = [],
                string $queue = 'default',
                int $priority = 5,
                ?int $delay = null,
                array $options = [],
            ): bool {
                $this->jobs[] = [
                    'class' => $jobClass,
                    'payload' => $payload,
                    'queue' => $queue,
                    'priority' => $priority,
                ];
                return true;
            }
        };

        ConcreteTestJob::setTestDispatcher($dispatcher);

        $result = ConcreteTestJob::dispatch(['key' => 'value']);

        $this->assertTrue($result);
        $this->assertCount(1, $dispatchedJobs);
        $this->assertEquals(ConcreteTestJob::class, $dispatchedJobs[0]['class']);
        $this->assertEquals(['key' => 'value'], $dispatchedJobs[0]['payload']);
    }

    #[Test]
    public function job_dispatch_uses_dispatcher_resolver_when_set(): void
    {
        $dispatchedJobs = [];

        $dispatcher = new class($dispatchedJobs) implements JobDispatcherInterface {
            public function __construct(private array &$jobs) {}

            public function add(
                string $jobClass,
                array $payload = [],
                string $queue = 'default',
                int $priority = 5,
                ?int $delay = null,
                array $options = [],
            ): bool {
                $this->jobs[] = ['class' => $jobClass, 'payload' => $payload];
                return true;
            }
        };

        ConcreteTestJob::setDispatcherResolver(fn() => $dispatcher);

        $result = ConcreteTestJob::dispatch(['test' => 'data']);

        $this->assertTrue($result);
        $this->assertCount(1, $dispatchedJobs);
    }

    #[Test]
    public function job_dispatch_sync_executes_without_dispatcher(): void
    {
        $result = ConcreteTestJob::dispatchSync(['sync' => 'payload']);

        $this->assertTrue($result);
        $this->assertEquals(['sync' => 'payload'], ConcreteTestJob::$lastHandledPayload);
    }

    #[Test]
    public function job_on_queue_returns_pending_dispatch(): void
    {
        $pending = ConcreteTestJob::onQueue('high-priority');

        $this->assertInstanceOf(PendingDispatch::class, $pending);
    }

    #[Test]
    public function job_with_priority_returns_pending_dispatch(): void
    {
        $pending = ConcreteTestJob::withPriority(10);

        $this->assertInstanceOf(PendingDispatch::class, $pending);
    }

    #[Test]
    public function job_later_returns_pending_dispatch(): void
    {
        $pending = ConcreteTestJob::later(60);

        $this->assertInstanceOf(PendingDispatch::class, $pending);
    }

    #[Test]
    public function pending_dispatch_uses_test_dispatcher_when_set(): void
    {
        $dispatchedJobs = [];

        $dispatcher = new class($dispatchedJobs) implements JobDispatcherInterface {
            public function __construct(private array &$jobs) {}

            public function add(
                string $jobClass,
                array $payload = [],
                string $queue = 'default',
                int $priority = 5,
                ?int $delay = null,
                array $options = [],
            ): bool {
                $this->jobs[] = [
                    'class' => $jobClass,
                    'queue' => $queue,
                    'priority' => $priority,
                    'delay' => $delay,
                ];
                return true;
            }
        };

        PendingDispatch::setTestDispatcher($dispatcher);

        $result = (new PendingDispatch(ConcreteTestJob::class))
            ->onQueue('emails')
            ->priority(8)
            ->delay(30)
            ->dispatch(['data' => 'test']);

        $this->assertTrue($result);
        $this->assertCount(1, $dispatchedJobs);
        $this->assertEquals('emails', $dispatchedJobs[0]['queue']);
        $this->assertEquals(8, $dispatchedJobs[0]['priority']);
        $this->assertEquals(30, $dispatchedJobs[0]['delay']);
    }

    #[Test]
    public function pending_dispatch_uses_dispatcher_resolver_when_set(): void
    {
        $dispatchedJobs = [];

        $dispatcher = new class($dispatchedJobs) implements JobDispatcherInterface {
            public function __construct(private array &$jobs) {}

            public function add(
                string $jobClass,
                array $payload = [],
                string $queue = 'default',
                int $priority = 5,
                ?int $delay = null,
                array $options = [],
            ): bool {
                $this->jobs[] = ['class' => $jobClass];
                return true;
            }
        };

        PendingDispatch::setDispatcherResolver(fn() => $dispatcher);

        $result = (new PendingDispatch(ConcreteTestJob::class))->dispatch();

        $this->assertTrue($result);
        $this->assertCount(1, $dispatchedJobs);
    }

    #[Test]
    public function pending_dispatch_chains_multiple_options(): void
    {
        $dispatchedJobs = [];

        $dispatcher = new class($dispatchedJobs) implements JobDispatcherInterface {
            public function __construct(private array &$jobs) {}

            public function add(
                string $jobClass,
                array $payload = [],
                string $queue = 'default',
                int $priority = 5,
                ?int $delay = null,
                array $options = [],
            ): bool {
                $this->jobs[] = [
                    'queue' => $queue,
                    'priority' => $priority,
                    'delay' => $delay,
                    'options' => $options,
                ];
                return true;
            }
        };

        PendingDispatch::setTestDispatcher($dispatcher);

        (new PendingDispatch(ConcreteTestJob::class))
            ->onQueue('high')
            ->priority(10)
            ->delay(120)
            ->maxAttempts(5)
            ->timeout(600)
            ->backoff('linear')
            ->retryAfter(30)
            ->tags(['important', 'email'])
            ->dispatch();

        $this->assertCount(1, $dispatchedJobs);
        $job = $dispatchedJobs[0];

        $this->assertEquals('high', $job['queue']);
        $this->assertEquals(10, $job['priority']);
        $this->assertEquals(120, $job['delay']);
        $this->assertEquals(5, $job['options']['max_attempts']);
        $this->assertEquals(600, $job['options']['timeout']);
        $this->assertEquals('linear', $job['options']['backoff_strategy']);
        $this->assertEquals(30, $job['options']['retry_delay']);
        $this->assertEquals(['important', 'email'], $job['options']['tags']);
    }
}

/**
 * Concrete job class for testing.
 */
class ConcreteTestJob extends Job
{
    public static ?array $lastHandledPayload = null;

    public function handle(array $payload): void
    {
        self::$lastHandledPayload = $payload;
    }
}
