<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Unit;

use Lalaz\Queue\Tests\Common\QueueUnitTestCase;
use Lalaz\Queue\Tests\Common\MockQueueDriver;
use Lalaz\Queue\Tests\Common\FakeJob;
use Lalaz\Queue\PendingDispatch;
use Lalaz\Queue\Contracts\JobInterface;
use PHPUnit\Framework\Attributes\Test;

class PendingDispatchTestJob implements JobInterface
{
    public function handle(array $payload): void
    {
        // no-op for testing
    }
}

/**
 * Unit tests for PendingDispatch class.
 *
 * Tests the fluent builder for job dispatch configuration.
 *
 * @package lalaz/queue
 */
class PendingDispatchTest extends QueueUnitTestCase
{
    private MockQueueDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = $this->createMockDriver();
        PendingDispatch::setTestDispatcher($this->driver);
    }

    protected function tearDown(): void
    {
        PendingDispatch::setTestDispatcher(null);
        PendingDispatch::setDispatcherResolver(null);
        parent::tearDown();
    }

    // =========================================================================
    // Constructor Tests
    // =========================================================================

    #[Test]
    public function it_creates_instance_with_job_class(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $this->assertInstanceOf(PendingDispatch::class, $pending);
    }

    #[Test]
    public function it_accepts_optional_queue_name_in_constructor(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class, 'emails');
        $pending->dispatch([]);

        $this->assertSame('emails', $this->driver->addedJobs[0]['queue']);
    }

    // =========================================================================
    // Method Chaining Tests
    // =========================================================================

    #[Test]
    public function it_returns_self_from_on_queue(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->onQueue('notifications');

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_returns_self_from_priority(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->priority(8);

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_returns_self_from_delay(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->delay(60);

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_returns_self_from_with_options(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->withOptions(['key' => 'value']);

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_returns_self_from_max_attempts(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->maxAttempts(5);

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_returns_self_from_timeout(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->timeout(120);

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_returns_self_from_backoff(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->backoff('exponential');

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_returns_self_from_retry_after(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->retryAfter(30);

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_returns_self_from_tags(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->tags(['important', 'email']);

        $this->assertSame($pending, $result);
    }

    #[Test]
    public function it_supports_full_method_chaining(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending
            ->onQueue('emails')
            ->priority(9)
            ->delay(300)
            ->maxAttempts(5)
            ->timeout(120)
            ->backoff('exponential')
            ->retryAfter(60)
            ->tags(['critical', 'user-notification']);

        $this->assertSame($pending, $result);
    }

    // =========================================================================
    // Queue Setting Tests
    // =========================================================================

    #[Test]
    public function it_sets_the_queue_name(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->onQueue('custom-queue')->dispatch([]);

        $this->assertSame('custom-queue', $this->driver->addedJobs[0]['queue']);
    }

    #[Test]
    public function it_uses_default_queue_of_default(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch([]);

        $this->assertSame('default', $this->driver->addedJobs[0]['queue']);
    }

    // =========================================================================
    // Priority Tests
    // =========================================================================

    #[Test]
    public function it_sets_priority_within_valid_range(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->priority(7)->dispatch([]);

        $this->assertSame(7, $this->driver->addedJobs[0]['priority']);
    }

    #[Test]
    public function it_clamps_priority_to_minimum_0(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->priority(-5)->dispatch([]);

        $this->assertSame(0, $this->driver->addedJobs[0]['priority']);
    }

    #[Test]
    public function it_clamps_priority_to_maximum_10(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->priority(15)->dispatch([]);

        $this->assertSame(10, $this->driver->addedJobs[0]['priority']);
    }

    #[Test]
    public function it_uses_default_priority_of_5(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch([]);

        $this->assertSame(5, $this->driver->addedJobs[0]['priority']);
    }

    // =========================================================================
    // Delay Tests
    // =========================================================================

    #[Test]
    public function it_sets_delay_in_seconds(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->delay(300)->dispatch([]);

        $this->assertSame(300, $this->driver->addedJobs[0]['delay']);
    }

    #[Test]
    public function it_has_null_delay_by_default(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch([]);

        $this->assertNull($this->driver->addedJobs[0]['delay']);
    }

    // =========================================================================
    // Options Tests
    // =========================================================================

    #[Test]
    public function it_merges_options_correctly(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->withOptions(['custom' => 'value']);
        $pending->withOptions(['another' => 'option']);
        $pending->dispatch([]);

        $options = $this->driver->addedJobs[0]['options'];
        $this->assertArrayHasKey('custom', $options);
        $this->assertArrayHasKey('another', $options);
        $this->assertSame('value', $options['custom']);
        $this->assertSame('option', $options['another']);
    }

    #[Test]
    public function it_sets_max_attempts_option(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->maxAttempts(10)->dispatch([]);

        $options = $this->driver->addedJobs[0]['options'];
        $this->assertSame(10, $options['max_attempts']);
    }

    #[Test]
    public function it_sets_timeout_option(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->timeout(180)->dispatch([]);

        $options = $this->driver->addedJobs[0]['options'];
        $this->assertSame(180, $options['timeout']);
    }

    #[Test]
    public function it_sets_backoff_strategy_option(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->backoff('linear')->dispatch([]);

        $options = $this->driver->addedJobs[0]['options'];
        $this->assertSame('linear', $options['backoff_strategy']);
    }

    #[Test]
    public function it_sets_retry_delay_option(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->retryAfter(45)->dispatch([]);

        $options = $this->driver->addedJobs[0]['options'];
        $this->assertSame(45, $options['retry_delay']);
    }

    #[Test]
    public function it_sets_tags_option(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->tags(['urgent', 'email', 'marketing'])->dispatch([]);

        $options = $this->driver->addedJobs[0]['options'];
        $this->assertSame(['urgent', 'email', 'marketing'], $options['tags']);
    }

    #[Test]
    public function it_has_empty_options_by_default(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch([]);

        $this->assertSame([], $this->driver->addedJobs[0]['options']);
    }

    // =========================================================================
    // Dispatch Tests
    // =========================================================================

    #[Test]
    public function it_dispatches_with_payload(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch(['user_id' => 123, 'action' => 'send']);

        $this->assertSame(['user_id' => 123, 'action' => 'send'], $this->driver->addedJobs[0]['payload']);
    }

    #[Test]
    public function it_dispatches_with_empty_payload(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch([]);

        $this->assertSame([], $this->driver->addedJobs[0]['payload']);
    }

    #[Test]
    public function it_dispatches_with_correct_job_class(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch([]);

        $this->assertSame(PendingDispatchTestJob::class, $this->driver->addedJobs[0]['jobClass']);
    }

    #[Test]
    public function it_returns_true_on_successful_dispatch(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->dispatch([]);

        $this->assertTrue($result);
    }

    #[Test]
    public function it_returns_false_when_driver_fails(): void
    {
        $this->driver->addShouldSucceed = false;
        $pending = new PendingDispatch(PendingDispatchTestJob::class);

        $result = $pending->dispatch([]);

        $this->assertFalse($result);
    }

    // =========================================================================
    // Full Configuration Tests
    // =========================================================================

    #[Test]
    public function it_dispatches_with_all_options_configured(): void
    {
        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending
            ->onQueue('high-priority')
            ->priority(10)
            ->delay(600)
            ->maxAttempts(5)
            ->timeout(300)
            ->backoff('exponential')
            ->retryAfter(120)
            ->tags(['critical', 'notification'])
            ->withOptions(['custom' => 'value'])
            ->dispatch(['id' => 1]);

        $job = $this->driver->addedJobs[0];
        $this->assertSame('high-priority', $job['queue']);
        $this->assertSame(10, $job['priority']);
        $this->assertSame(600, $job['delay']);
        $this->assertSame(['id' => 1], $job['payload']);

        $options = $job['options'];
        $this->assertSame(5, $options['max_attempts']);
        $this->assertSame(300, $options['timeout']);
        $this->assertSame('exponential', $options['backoff_strategy']);
        $this->assertSame(120, $options['retry_delay']);
        $this->assertSame(['critical', 'notification'], $options['tags']);
        $this->assertSame('value', $options['custom']);
    }

    // =========================================================================
    // Test Dispatcher Tests
    // =========================================================================

    #[Test]
    public function it_uses_test_dispatcher_when_set(): void
    {
        $customDriver = new MockQueueDriver();
        PendingDispatch::setTestDispatcher($customDriver);

        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch(['custom' => true]);

        $this->assertCount(1, $customDriver->addedJobs);
        $this->assertCount(0, $this->driver->addedJobs);
    }

    #[Test]
    public function it_uses_dispatcher_resolver_when_set(): void
    {
        PendingDispatch::setTestDispatcher(null);
        $customDriver = new MockQueueDriver();
        PendingDispatch::setDispatcherResolver(fn() => $customDriver);

        $pending = new PendingDispatch(PendingDispatchTestJob::class);
        $pending->dispatch(['resolved' => true]);

        $this->assertCount(1, $customDriver->addedJobs);
    }
}
