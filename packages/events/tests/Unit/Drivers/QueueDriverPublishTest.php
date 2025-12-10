<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Drivers\QueueDriver;

/**
 * Unit tests for QueueDriver publish method
 *
 * Tests the publish functionality using the injectable dispatcher
 * to capture and verify dispatch calls without needing a real queue.
 */
final class QueueDriverPublishTest extends EventsUnitTestCase
{
    /** @var array<int, array{payload: array, queue: string, priority: int, delay: ?int}> */
    private array $dispatchedJobs = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatchedJobs = [];
    }

    /**
     * Create a QueueDriver with a capturing dispatcher.
     */
    private function createDriver(
        string $queue = 'events',
        int $priority = 9,
        ?int $delay = null
    ): QueueDriver {
        return new QueueDriver(
            queue: $queue,
            priority: $priority,
            delay: $delay,
            dispatcher: function (array $payload, string $queue, int $priority, ?int $delay) {
                $this->dispatchedJobs[] = [
                    'payload' => $payload,
                    'queue' => $queue,
                    'priority' => $priority,
                    'delay' => $delay,
                ];
            }
        );
    }

    #[Test]
    public function it_builds_correct_payload_with_string_data(): void
    {
        $driver = $this->createDriver();

        $driver->publish('user.created', 'simple string data');

        $this->assertCount(1, $this->dispatchedJobs);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('events', $job['queue']);
        $this->assertSame(9, $job['priority']);
        $this->assertNull($job['delay']);
        $this->assertSame('user.created', $job['payload']['event_name']);
        $this->assertSame('simple string data', $job['payload']['event_data']);
        $this->assertArrayHasKey('published_at', $job['payload']);
    }

    #[Test]
    public function it_builds_correct_payload_with_array_data(): void
    {
        $driver = $this->createDriver();

        $data = ['id' => 123, 'name' => 'John'];
        $driver->publish('user.created', $data);

        $job = $this->dispatchedJobs[0];
        $this->assertSame(json_encode($data), $job['payload']['event_data']);
    }

    #[Test]
    public function it_uses_options_queue_over_default(): void
    {
        $driver = $this->createDriver(queue: 'default-queue');

        $driver->publish('event.test', [], ['queue' => 'custom-queue']);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('custom-queue', $job['queue']);
    }

    #[Test]
    public function it_uses_options_priority_over_default(): void
    {
        $driver = $this->createDriver(priority: 9);

        $driver->publish('event.test', [], ['priority' => 1]);

        $job = $this->dispatchedJobs[0];
        $this->assertSame(1, $job['priority']);
    }

    #[Test]
    public function it_uses_options_delay_over_default(): void
    {
        $driver = $this->createDriver(delay: 60);

        $driver->publish('event.test', [], ['delay' => 120]);

        $job = $this->dispatchedJobs[0];
        $this->assertSame(120, $job['delay']);
    }

    #[Test]
    public function it_uses_default_queue_when_no_option_provided(): void
    {
        $driver = $this->createDriver(queue: 'my-events');

        $driver->publish('event.test', []);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('my-events', $job['queue']);
    }

    #[Test]
    public function it_uses_default_priority_when_no_option_provided(): void
    {
        $driver = $this->createDriver(priority: 5);

        $driver->publish('event.test', []);

        $job = $this->dispatchedJobs[0];
        $this->assertSame(5, $job['priority']);
    }

    #[Test]
    public function it_sets_delay_only_when_configured(): void
    {
        $driverWithDelay = $this->createDriver(delay: 30);
        $driverWithoutDelay = $this->createDriver();

        $driverWithDelay->publish('event.test', []);

        $jobWithDelay = $this->dispatchedJobs[0];
        $this->assertSame(30, $jobWithDelay['delay']);

        $this->dispatchedJobs = [];
        $driverWithoutDelay->publish('event.test', []);

        $jobWithoutDelay = $this->dispatchedJobs[0];
        $this->assertNull($jobWithoutDelay['delay']);
    }

    #[Test]
    public function it_handles_nested_array_data(): void
    {
        $driver = $this->createDriver();

        $data = [
            'user' => [
                'id' => 1,
                'profile' => [
                    'name' => 'John',
                    'settings' => ['notifications' => true]
                ]
            ]
        ];

        $driver->publish('user.updated', $data);

        $job = $this->dispatchedJobs[0];
        $this->assertSame(json_encode($data), $job['payload']['event_data']);
    }

    #[Test]
    public function it_handles_object_data_via_json_encode(): void
    {
        $driver = $this->createDriver();

        $data = (object) ['id' => 1, 'name' => 'John'];
        $driver->publish('user.created', $data);

        $job = $this->dispatchedJobs[0];
        $this->assertSame(json_encode($data), $job['payload']['event_data']);
    }

    #[Test]
    public function it_handles_null_data(): void
    {
        $driver = $this->createDriver();

        $driver->publish('event.test', null);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('null', $job['payload']['event_data']);
    }

    #[Test]
    public function it_handles_boolean_data(): void
    {
        $driver = $this->createDriver();

        $driver->publish('event.test', true);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('true', $job['payload']['event_data']);
    }

    #[Test]
    public function it_handles_integer_data(): void
    {
        $driver = $this->createDriver();

        $driver->publish('event.test', 42);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('42', $job['payload']['event_data']);
    }

    #[Test]
    public function it_includes_published_at_timestamp(): void
    {
        $driver = $this->createDriver();

        $before = date('Y-m-d H:i:s');
        $driver->publish('event.test', []);
        $after = date('Y-m-d H:i:s');

        $job = $this->dispatchedJobs[0];
        $publishedAt = $job['payload']['published_at'];

        $this->assertGreaterThanOrEqual($before, $publishedAt);
        $this->assertLessThanOrEqual($after, $publishedAt);
    }

    #[Test]
    public function it_can_dispatch_multiple_events(): void
    {
        $driver = $this->createDriver();

        $driver->publish('event.one', ['id' => 1]);
        $driver->publish('event.two', ['id' => 2]);
        $driver->publish('event.three', ['id' => 3]);

        $this->assertCount(3, $this->dispatchedJobs);
        $this->assertSame('event.one', $this->dispatchedJobs[0]['payload']['event_name']);
        $this->assertSame('event.two', $this->dispatchedJobs[1]['payload']['event_name']);
        $this->assertSame('event.three', $this->dispatchedJobs[2]['payload']['event_name']);
    }

    #[Test]
    public function it_handles_unicode_data(): void
    {
        $driver = $this->createDriver();

        $data = ['message' => '你好世界 🌍 مرحبا'];
        $driver->publish('event.unicode', $data);

        $job = $this->dispatchedJobs[0];
        $decoded = json_decode($job['payload']['event_data'], true);
        $this->assertSame('你好世界 🌍 مرحبا', $decoded['message']);
    }

    #[Test]
    public function it_handles_empty_array_data(): void
    {
        $driver = $this->createDriver();

        $driver->publish('event.test', []);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('[]', $job['payload']['event_data']);
    }

    #[Test]
    public function it_handles_empty_string_data(): void
    {
        $driver = $this->createDriver();

        $driver->publish('event.test', '');

        $job = $this->dispatchedJobs[0];
        $this->assertSame('', $job['payload']['event_data']);
    }

    #[Test]
    public function it_preserves_all_options(): void
    {
        $driver = $this->createDriver();

        $driver->publish('event.test', [], [
            'queue' => 'high-priority',
            'priority' => 1,
            'delay' => 300,
            'extra' => 'ignored'  // Extra options should not affect core functionality
        ]);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('high-priority', $job['queue']);
        $this->assertSame(1, $job['priority']);
        $this->assertSame(300, $job['delay']);
    }

    #[Test]
    public function it_handles_zero_delay_from_options(): void
    {
        $driver = $this->createDriver(delay: 60);

        $driver->publish('event.test', [], ['delay' => 0]);

        $job = $this->dispatchedJobs[0];
        $this->assertSame(0, $job['delay']);
    }

    #[Test]
    public function it_handles_zero_priority_from_options(): void
    {
        $driver = $this->createDriver(priority: 5);

        $driver->publish('event.test', [], ['priority' => 0]);

        $job = $this->dispatchedJobs[0];
        $this->assertSame(0, $job['priority']);
    }

    #[Test]
    public function it_handles_float_data(): void
    {
        $driver = $this->createDriver();

        $driver->publish('event.test', 3.14159);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('3.14159', $job['payload']['event_data']);
    }

    #[Test]
    public function it_handles_large_payload(): void
    {
        $driver = $this->createDriver();

        $data = ['items' => array_fill(0, 1000, ['id' => 1, 'name' => 'test'])];
        $driver->publish('event.large', $data);

        $job = $this->dispatchedJobs[0];
        $decoded = json_decode($job['payload']['event_data'], true);
        $this->assertCount(1000, $decoded['items']);
    }

    #[Test]
    public function it_handles_special_characters_in_event_name(): void
    {
        $driver = $this->createDriver();

        $driver->publish('namespace:event.sub-type_v2', []);

        $job = $this->dispatchedJobs[0];
        $this->assertSame('namespace:event.sub-type_v2', $job['payload']['event_name']);
    }
}
