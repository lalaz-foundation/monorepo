<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Events;
use Lalaz\Events\EventHub;

/**
 * Additional unit tests for Events Facade
 *
 * Tests edge cases and fallback behavior
 */
final class EventsFacadeTest extends EventsUnitTestCase
{
    protected function tearDown(): void
    {
        Events::setInstance(null);
        parent::tearDown();
    }

    #[Test]
    public function it_throws_when_no_instance_set(): void
    {
        Events::setInstance(null);

        // getInstance should throw RuntimeException when no instance and no Application context
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No application context available');
        
        Events::getInstance();
    }

    #[Test]
    public function it_can_set_null_instance(): void
    {
        Events::setInstance(new EventHub());
        Events::setInstance(null);

        // Should not crash
        $this->assertTrue(true);
    }

    #[Test]
    public function it_replaces_existing_instance(): void
    {
        $hub1 = new EventHub();
        $hub2 = new EventHub();

        Events::setInstance($hub1);
        $this->assertSame($hub1, Events::getInstance());

        Events::setInstance($hub2);
        $this->assertSame($hub2, Events::getInstance());
    }

    #[Test]
    public function it_handles_trigger_with_various_payload_types(): void
    {
        Events::setInstance(EventHub::syncOnly());

        $received = [];
        Events::register('test.payloads', function ($data) use (&$received) {
            $received[] = $data;
        });

        // Array payload
        Events::triggerSync('test.payloads', ['key' => 'value']);

        // Object payload
        $obj = new \stdClass();
        $obj->prop = 'test';
        Events::triggerSync('test.payloads', $obj);

        // String payload
        Events::triggerSync('test.payloads', 'string data');

        // Integer payload
        Events::triggerSync('test.payloads', 42);

        // Null payload
        Events::triggerSync('test.payloads', null);

        $this->assertCount(5, $received);
        $this->assertSame(['key' => 'value'], $received[0]);
        $this->assertSame($obj, $received[1]);
        $this->assertSame('string data', $received[2]);
        $this->assertSame(42, $received[3]);
        $this->assertNull($received[4]);
    }

    #[Test]
    public function it_returns_correct_listeners_array(): void
    {
        Events::setInstance(new EventHub());

        $listener1 = fn() => null;
        $listener2 = fn() => null;

        Events::register('test.list', $listener1);
        Events::register('test.list', $listener2);

        $listeners = Events::getListeners('test.list');

        $this->assertCount(2, $listeners);
        $this->assertContains($listener1, $listeners);
        $this->assertContains($listener2, $listeners);
    }

    #[Test]
    public function it_handles_event_with_special_characters_in_name(): void
    {
        Events::setInstance(EventHub::syncOnly());

        $received = null;
        Events::register('app:user.created@v2', function ($data) use (&$received) {
            $received = $data;
        });

        Events::triggerSync('app:user.created@v2', ['special' => true]);

        $this->assertSame(['special' => true], $received);
    }

    #[Test]
    public function it_handles_empty_event_name(): void
    {
        Events::setInstance(EventHub::syncOnly());

        $received = null;
        Events::register('', function ($data) use (&$received) {
            $received = $data;
        });

        Events::triggerSync('', ['empty' => 'name']);

        $this->assertSame(['empty' => 'name'], $received);
    }

    #[Test]
    public function it_maintains_listener_order_by_priority(): void
    {
        Events::setInstance(new EventHub());

        $order = [];

        Events::register('test.order', function () use (&$order) {
            $order[] = 'first_added_low';
        }, 0);

        Events::register('test.order', function () use (&$order) {
            $order[] = 'second_added_high';
        }, 100);

        Events::register('test.order', function () use (&$order) {
            $order[] = 'third_added_medium';
        }, 50);

        Events::triggerSync('test.order', []);

        $this->assertSame(['second_added_high', 'third_added_medium', 'first_added_low'], $order);
    }

    #[Test]
    public function forget_with_null_listener_removes_all(): void
    {
        Events::setInstance(new EventHub());

        Events::register('test.remove', fn() => null);
        Events::register('test.remove', fn() => null);
        Events::register('test.remove', fn() => null);

        $this->assertTrue(Events::hasListeners('test.remove'));

        Events::forget('test.remove', null);

        $this->assertFalse(Events::hasListeners('test.remove'));
    }
}
