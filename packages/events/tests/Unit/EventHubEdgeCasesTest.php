<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\EventHub;
use Lalaz\Events\Drivers\NullDriver;

/**
 * Additional unit tests for EventHub
 *
 * Tests edge cases and advanced functionality
 */
final class EventHubEdgeCasesTest extends EventsUnitTestCase
{
    #[Test]
    public function it_handles_event_with_no_registered_listeners(): void
    {
        $hub = new EventHub();

        // Should not throw
        $hub->triggerSync('nonexistent.event', ['data' => 'test']);

        $this->assertTrue(true);
    }

    #[Test]
    public function it_handles_trigger_with_empty_data(): void
    {
        $hub = new EventHub();
        $received = null;

        $hub->register('test.empty', function ($data) use (&$received) {
            $received = $data;
        });

        $hub->triggerSync('test.empty', []);

        $this->assertSame([], $received);
    }

    #[Test]
    public function it_handles_registering_same_listener_multiple_times(): void
    {
        $hub = new EventHub();
        $count = 0;

        $listener = function () use (&$count) {
            $count++;
        };

        $hub->register('test.duplicate', $listener);
        $hub->register('test.duplicate', $listener);

        $hub->triggerSync('test.duplicate', []);

        // Same listener registered twice should execute twice
        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_handles_nested_event_triggers(): void
    {
        $hub = new EventHub();
        $order = [];

        $hub->register('outer.event', function () use ($hub, &$order) {
            $order[] = 'outer_start';
            $hub->triggerSync('inner.event', []);
            $order[] = 'outer_end';
        });

        $hub->register('inner.event', function () use (&$order) {
            $order[] = 'inner';
        });

        $hub->triggerSync('outer.event', []);

        $this->assertSame(['outer_start', 'inner', 'outer_end'], $order);
    }

    #[Test]
    public function it_handles_listener_that_registers_new_listener(): void
    {
        $hub = new EventHub();
        $secondListenerCalled = false;

        $hub->register('first.event', function () use ($hub, &$secondListenerCalled) {
            $hub->register('second.event', function () use (&$secondListenerCalled) {
                $secondListenerCalled = true;
            });
        });

        $hub->triggerSync('first.event', []);
        $hub->triggerSync('second.event', []);

        $this->assertTrue($secondListenerCalled);
    }

    #[Test]
    public function it_handles_listener_that_forgets_itself(): void
    {
        $hub = new EventHub();
        $callCount = 0;

        $listener = null;
        $listener = function () use ($hub, &$callCount, &$listener) {
            $callCount++;
            $hub->forget('self.forget', $listener);
        };

        $hub->register('self.forget', $listener);

        $hub->triggerSync('self.forget', []);
        $hub->triggerSync('self.forget', []);

        // Should only be called once because it removes itself
        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function it_handles_complex_payload_objects(): void
    {
        $hub = new EventHub();
        $received = null;

        $hub->register('complex.payload', function ($data) use (&$received) {
            $received = $data;
        });

        $complexPayload = [
            'user' => (object) ['id' => 1, 'name' => 'John'],
            'items' => [
                ['id' => 1, 'qty' => 2],
                ['id' => 2, 'qty' => 1],
            ],
            'metadata' => [
                'timestamp' => time(),
                'source' => 'api',
                'nested' => [
                    'deep' => [
                        'value' => true,
                    ],
                ],
            ],
        ];

        $hub->triggerSync('complex.payload', $complexPayload);

        $this->assertEquals($complexPayload, $received);
    }

    #[Test]
    public function it_maintains_async_state_through_operations(): void
    {
        $hub = new EventHub();

        $this->assertTrue($hub->isAsyncEnabled());

        $hub->setAsyncEnabled(false);
        $this->assertFalse($hub->isAsyncEnabled());

        // Register/trigger shouldn't affect state
        $hub->register('test', fn() => null);
        $this->assertFalse($hub->isAsyncEnabled());

        $hub->triggerSync('test', []);
        $this->assertFalse($hub->isAsyncEnabled());

        $hub->setAsyncEnabled(true);
        $this->assertTrue($hub->isAsyncEnabled());
    }

    #[Test]
    public function it_handles_unicode_event_names(): void
    {
        $hub = new EventHub();
        $received = null;

        $hub->register('événement.créé', function ($data) use (&$received) {
            $received = $data;
        });

        $hub->triggerSync('événement.créé', ['unicode' => '你好']);

        $this->assertSame(['unicode' => '你好'], $received);
    }

    #[Test]
    public function it_handles_very_long_event_names(): void
    {
        $hub = new EventHub();
        $received = null;

        $longName = str_repeat('event.', 100) . 'final';

        $hub->register($longName, function ($data) use (&$received) {
            $received = $data;
        });

        $hub->triggerSync($longName, ['long' => true]);

        $this->assertSame(['long' => true], $received);
    }

    #[Test]
    public function it_handles_null_callable_gracefully(): void
    {
        $hub = new EventHub();
        $executed = false;

        $hub->register('test.null', function ($data) use (&$executed) {
            $executed = true;
            return null;
        });

        $hub->triggerSync('test.null', ['test' => true]);

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_handles_closure_with_use_by_reference(): void
    {
        $hub = new EventHub();
        $counter = 0;

        $hub->register('counter.event', function () use (&$counter) {
            $counter++;
        });

        $hub->triggerSync('counter.event', []);
        $hub->triggerSync('counter.event', []);
        $hub->triggerSync('counter.event', []);

        $this->assertSame(3, $counter);
    }
}
