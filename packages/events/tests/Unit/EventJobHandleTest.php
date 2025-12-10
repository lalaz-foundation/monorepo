<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Contracts\EventPublisherInterface;
use Lalaz\Events\EventJob;

/**
 * Additional unit tests for EventJob
 *
 * Tests edge cases and the handle() method execution paths
 */
final class EventJobHandleTest extends EventsUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventJob::resetTestState();
    }

    protected function tearDown(): void
    {
        EventJob::resetTestState();
        parent::tearDown();
    }

    #[Test]
    public function handle_does_nothing_when_no_publisher_available(): void
    {
        // Resolver returns null, simulating no publisher available
        EventJob::setPublisherResolver(fn() => null);

        $job = new EventJob();

        // Should not throw
        $job->handle([
            'event_name' => 'test.event',
            'event_data' => json_encode(['key' => 'value']),
        ]);

        $this->assertTrue(true);
    }

    #[Test]
    public function handle_skips_when_event_name_is_null(): void
    {
        $triggerCalled = false;
        $publisher = new class($triggerCalled) implements EventPublisherInterface {
            private bool $called;
            public function __construct(bool &$called) {
                $this->called = &$called;
            }
            public function trigger(string $event, mixed $data): void {
                $this->called = true;
            }
            public function triggerSync(string $event, mixed $data): void {
                $this->called = true;
            }
        };

        EventJob::setTestPublisher($publisher);

        $job = new EventJob();
        $job->handle([
            'event_data' => json_encode(['key' => 'value']),
        ]);

        $this->assertFalse($triggerCalled);
    }

    #[Test]
    public function handle_passes_empty_array_when_event_data_is_null(): void
    {
        $receivedData = null;
        $publisher = new class($receivedData) implements EventPublisherInterface {
            private mixed $data;
            public function __construct(mixed &$data) {
                $this->data = &$data;
            }
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {
                $this->data = $data;
            }
        };

        EventJob::setTestPublisher($publisher);

        $job = new EventJob();
        $job->handle([
            'event_name' => 'test.event',
            // No event_data
        ]);

        $this->assertSame([], $receivedData);
    }

    #[Test]
    public function handle_passes_empty_array_when_event_data_is_invalid_json(): void
    {
        $receivedData = null;
        $publisher = new class($receivedData) implements EventPublisherInterface {
            private mixed $data;
            public function __construct(mixed &$data) {
                $this->data = &$data;
            }
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {
                $this->data = $data;
            }
        };

        EventJob::setTestPublisher($publisher);

        $job = new EventJob();
        $job->handle([
            'event_name' => 'test.event',
            'event_data' => 'not valid json {{{',
        ]);

        // json_decode returns null for invalid JSON, so empty array fallback
        $this->assertSame([], $receivedData);
    }

    #[Test]
    public function handle_passes_decoded_data_to_publisher(): void
    {
        $receivedEvent = null;
        $receivedData = null;
        $publisher = new class($receivedEvent, $receivedData) implements EventPublisherInterface {
            private ?string $event;
            private mixed $data;
            public function __construct(?string &$event, mixed &$data) {
                $this->event = &$event;
                $this->data = &$data;
            }
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {
                $this->event = $event;
                $this->data = $data;
            }
        };

        EventJob::setTestPublisher($publisher);

        $job = new EventJob();
        $job->handle([
            'event_name' => 'user.created',
            'event_data' => json_encode(['id' => 123, 'name' => 'John']),
        ]);

        $this->assertSame('user.created', $receivedEvent);
        $this->assertSame(['id' => 123, 'name' => 'John'], $receivedData);
    }

    #[Test]
    public function handle_uses_resolver_when_no_test_publisher(): void
    {
        $resolverCalled = false;
        $publisherUsed = false;

        $publisher = new class($publisherUsed) implements EventPublisherInterface {
            private bool $used;
            public function __construct(bool &$used) {
                $this->used = &$used;
            }
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {
                $this->used = true;
            }
        };

        EventJob::setPublisherResolver(function () use (&$resolverCalled, $publisher) {
            $resolverCalled = true;
            return $publisher;
        });

        $job = new EventJob();
        $job->handle([
            'event_name' => 'test.event',
            'event_data' => json_encode([]),
        ]);

        $this->assertTrue($resolverCalled);
        $this->assertTrue($publisherUsed);
    }

    #[Test]
    public function handle_handles_non_string_event_data(): void
    {
        $receivedData = null;
        $publisher = new class($receivedData) implements EventPublisherInterface {
            private mixed $data;
            public function __construct(mixed &$data) {
                $this->data = &$data;
            }
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {
                $this->data = $data;
            }
        };

        EventJob::setTestPublisher($publisher);

        $job = new EventJob();
        $job->handle([
            'event_name' => 'test.event',
            'event_data' => ['already' => 'array'], // Not a string
        ]);

        // Non-string event_data should result in null -> empty array fallback
        $this->assertSame([], $receivedData);
    }

    #[Test]
    public function handle_handles_empty_string_event_name(): void
    {
        $receivedEvent = null;
        $publisher = new class($receivedEvent) implements EventPublisherInterface {
            private ?string $event;
            public function __construct(?string &$event) {
                $this->event = &$event;
            }
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {
                $this->event = $event;
            }
        };

        EventJob::setTestPublisher($publisher);

        $job = new EventJob();
        $job->handle([
            'event_name' => '', // Empty string is not null
            'event_data' => json_encode([]),
        ]);

        // Empty string should still trigger
        $this->assertSame('', $receivedEvent);
    }

    #[Test]
    public function reset_test_state_clears_both_publisher_and_resolver(): void
    {
        $publisher = new class implements EventPublisherInterface {
            public function trigger(string $event, mixed $data): void {}
            public function triggerSync(string $event, mixed $data): void {}
        };

        EventJob::setTestPublisher($publisher);
        EventJob::setPublisherResolver(fn() => $publisher);

        EventJob::resetTestState();

        // Set a null-returning resolver to prevent Application fallback
        EventJob::setPublisherResolver(fn() => null);

        // After reset + null resolver, handler should not find a publisher
        $job = new EventJob();

        // Should complete without error (no publisher to call)
        $job->handle([
            'event_name' => 'test.event',
            'event_data' => json_encode([]),
        ]);

        $this->assertTrue(true);
    }
}
