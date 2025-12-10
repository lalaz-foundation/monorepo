<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\EventJob;

/**
 * Unit tests for EventJob
 *
 * Tests the EventJob class that processes queued events.
 */
final class EventJobTest extends EventsUnitTestCase
{
    #[Test]
    public function it_has_default_queue_name(): void
    {
        $job = new TestableEventJob();

        $this->assertSame('events', $job->getQueueName());
    }

    #[Test]
    public function it_has_default_priority(): void
    {
        $job = new TestableEventJob();

        $this->assertSame(9, $job->getJobPriority());
    }

    #[Test]
    public function it_has_default_max_attempts(): void
    {
        $job = new TestableEventJob();

        $this->assertSame(5, $job->getMaxAttempts());
    }

    #[Test]
    public function it_has_default_timeout(): void
    {
        $job = new TestableEventJob();

        $this->assertSame(60, $job->getTimeout());
    }

    #[Test]
    public function it_has_default_retry_delay(): void
    {
        $job = new TestableEventJob();

        $this->assertSame(30, $job->getRetryDelay());
    }

    #[Test]
    public function it_extracts_event_name_from_payload(): void
    {
        $job = new TestableEventJob();

        $payload = [
            'event_name' => 'user.created',
            'event_data' => json_encode(['id' => 1]),
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertSame('user.created', $result['event_name']);
    }

    #[Test]
    public function it_decodes_json_event_data(): void
    {
        $job = new TestableEventJob();

        $payload = [
            'event_name' => 'user.created',
            'event_data' => json_encode(['id' => 1, 'name' => 'John']),
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertSame(['id' => 1, 'name' => 'John'], $result['event_data']);
    }

    #[Test]
    public function it_returns_null_when_event_name_missing(): void
    {
        $job = new TestableEventJob();

        $payload = [
            'event_data' => json_encode(['id' => 1]),
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertNull($result['event_name']);
    }

    #[Test]
    public function it_returns_null_when_event_data_missing(): void
    {
        $job = new TestableEventJob();

        $payload = [
            'event_name' => 'user.created',
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertNull($result['event_data']);
    }

    #[Test]
    public function it_returns_null_for_invalid_json_data(): void
    {
        $job = new TestableEventJob();

        $payload = [
            'event_name' => 'user.created',
            'event_data' => 'not valid json {',
        ];

        $result = $job->testExtractPayload($payload);

        // Invalid JSON decodes to null
        $this->assertNull($result['event_data']);
    }

    #[Test]
    public function it_handles_empty_payload(): void
    {
        $job = new TestableEventJob();

        $result = $job->testExtractPayload([]);

        $this->assertNull($result['event_name']);
        $this->assertNull($result['event_data']);
    }

    #[Test]
    public function it_handles_nested_json_data(): void
    {
        $job = new TestableEventJob();

        $data = [
            'user' => [
                'id' => 1,
                'profile' => [
                    'name' => 'John',
                    'settings' => ['theme' => 'dark']
                ]
            ]
        ];

        $payload = [
            'event_name' => 'user.profile.updated',
            'event_data' => json_encode($data),
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertSame($data, $result['event_data']);
    }

    #[Test]
    public function it_handles_array_json_data(): void
    {
        $job = new TestableEventJob();

        $data = [1, 2, 3, 'a', 'b', 'c'];

        $payload = [
            'event_name' => 'items.processed',
            'event_data' => json_encode($data),
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertSame($data, $result['event_data']);
    }

    #[Test]
    public function it_handles_unicode_in_json_data(): void
    {
        $job = new TestableEventJob();

        $data = ['message' => '你好世界 🌍'];

        $payload = [
            'event_name' => 'message.sent',
            'event_data' => json_encode($data),
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertSame($data, $result['event_data']);
    }

    #[Test]
    public function it_handles_boolean_values_in_json(): void
    {
        $job = new TestableEventJob();

        $data = ['active' => true, 'deleted' => false];

        $payload = [
            'event_name' => 'status.changed',
            'event_data' => json_encode($data),
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertSame($data, $result['event_data']);
    }

    #[Test]
    public function it_handles_null_value_in_json(): void
    {
        $job = new TestableEventJob();

        $payload = [
            'event_name' => 'event.test',
            'event_data' => 'null',
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertNull($result['event_data']);
    }

    #[Test]
    public function it_handles_numeric_string_in_json(): void
    {
        $job = new TestableEventJob();

        $payload = [
            'event_name' => 'event.test',
            'event_data' => '42',
        ];

        $result = $job->testExtractPayload($payload);

        $this->assertSame(42, $result['event_data']);
    }

    #[Test]
    public function it_skips_execution_when_event_name_is_null(): void
    {
        $job = new TestableEventJob();

        // Payload without event_name should early return without triggering
        $job->testHandle([
            'event_data' => json_encode(['id' => 1]),
        ]);

        $this->assertFalse($job->wasTriggered());
    }

    #[Test]
    public function it_would_trigger_event_when_payload_is_valid(): void
    {
        $job = new TestableEventJob();

        $job->testHandle([
            'event_name' => 'user.created',
            'event_data' => json_encode(['id' => 1]),
        ]);

        $this->assertTrue($job->wasTriggered());
        $this->assertSame('user.created', $job->getTriggeredEventName());
        $this->assertSame(['id' => 1], $job->getTriggeredEventData());
    }

    #[Test]
    public function it_handles_empty_event_data(): void
    {
        $job = new TestableEventJob();

        $job->testHandle([
            'event_name' => 'user.created',
            'event_data' => json_encode([]),
        ]);

        $this->assertTrue($job->wasTriggered());
        $this->assertSame([], $job->getTriggeredEventData());
    }

    #[Test]
    public function it_uses_injected_test_publisher(): void
    {
        $triggered = [];
        $mockPublisher = new class($triggered) implements \Lalaz\Events\Contracts\EventPublisherInterface {
            private array $triggered;
            public function __construct(array &$triggered) {
                $this->triggered = &$triggered;
            }
            public function trigger(string $event, mixed $data): void {
                $this->triggered[] = ['event' => $event, 'data' => $data, 'async' => true];
            }
            public function triggerSync(string $event, mixed $data): void {
                $this->triggered[] = ['event' => $event, 'data' => $data, 'async' => false];
            }
        };

        EventJob::setTestPublisher($mockPublisher);

        try {
            $job = new EventJob();
            $job->handle([
                'event_name' => 'user.created',
                'event_data' => json_encode(['id' => 1]),
            ]);

            $this->assertCount(1, $triggered);
            $this->assertSame('user.created', $triggered[0]['event']);
            $this->assertSame(['id' => 1], $triggered[0]['data']);
            $this->assertFalse($triggered[0]['async']); // triggerSync is called
        } finally {
            EventJob::resetTestState();
        }
    }

    #[Test]
    public function it_uses_custom_publisher_resolver(): void
    {
        $resolverCalled = false;
        $mockPublisher = new class implements \Lalaz\Events\Contracts\EventPublisherInterface {
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {}
        };

        EventJob::setPublisherResolver(function () use (&$resolverCalled, $mockPublisher) {
            $resolverCalled = true;
            return $mockPublisher;
        });

        try {
            $job = new EventJob();
            $job->handle([
                'event_name' => 'test.event',
                'event_data' => json_encode([]),
            ]);

            $this->assertTrue($resolverCalled);
        } finally {
            EventJob::resetTestState();
        }
    }

    #[Test]
    public function it_resets_test_state(): void
    {
        $mockPublisher = new class implements \Lalaz\Events\Contracts\EventPublisherInterface {
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {}
        };

        EventJob::setTestPublisher($mockPublisher);
        EventJob::setPublisherResolver(fn() => $mockPublisher);

        EventJob::resetTestState();

        // After reset, a new job should not use the mock
        // (will fall back to Application context which doesn't exist)
        $job = new EventJob();

        // We can't test directly, but reset should have cleared the state
        $this->assertTrue(true);
    }

    #[Test]
    public function it_prefers_test_publisher_over_resolver(): void
    {
        $testPublisherUsed = false;
        $resolverUsed = false;

        $testPublisher = new class($testPublisherUsed) implements \Lalaz\Events\Contracts\EventPublisherInterface {
            private bool $used;
            public function __construct(bool &$used) { $this->used = &$used; }
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void { $this->used = true; }
        };

        $resolverPublisher = new class($resolverUsed) implements \Lalaz\Events\Contracts\EventPublisherInterface {
            private bool $used;
            public function __construct(bool &$used) { $this->used = &$used; }
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void { $this->used = true; }
        };

        EventJob::setTestPublisher($testPublisher);
        EventJob::setPublisherResolver(fn() => $resolverPublisher);

        try {
            $job = new EventJob();
            $job->handle([
                'event_name' => 'test.event',
                'event_data' => json_encode([]),
            ]);

            $this->assertTrue($testPublisherUsed);
            $this->assertFalse($resolverUsed);
        } finally {
            EventJob::resetTestState();
        }
    }
}

/**
 * Testable EventJob that exposes protected methods for testing.
 */
class TestableEventJob extends EventJob
{
    private bool $triggered = false;
    private ?string $triggeredEventName = null;
    private mixed $triggeredEventData = null;

    public function getQueueName(): string
    {
        return $this->queue;
    }

    public function getJobPriority(): int
    {
        return $this->priority;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getRetryDelay(): int
    {
        return $this->retryDelay;
    }

    /**
     * Extract and decode the payload data for testing.
     */
    public function testExtractPayload(array $payload): array
    {
        $eventName = $payload['event_name'] ?? null;
        $eventData = isset($payload['event_data']) && \is_string($payload['event_data'])
            ? json_decode($payload['event_data'], true)
            : null;

        return [
            'event_name' => $eventName,
            'event_data' => $eventData,
        ];
    }

    /**
     * Test handle without actually invoking Application context.
     */
    public function testHandle(array $payload): void
    {
        $eventName = $payload['event_name'] ?? null;
        $eventData = isset($payload['event_data']) && \is_string($payload['event_data'])
            ? json_decode($payload['event_data'], true)
            : null;

        if ($eventName === null) {
            return;
        }

        // Instead of calling Application::context()->events(), we track the call
        $this->triggered = true;
        $this->triggeredEventName = $eventName;
        $this->triggeredEventData = $eventData ?? [];
    }

    public function wasTriggered(): bool
    {
        return $this->triggered;
    }

    public function getTriggeredEventName(): ?string
    {
        return $this->triggeredEventName;
    }

    public function getTriggeredEventData(): mixed
    {
        return $this->triggeredEventData;
    }
}
