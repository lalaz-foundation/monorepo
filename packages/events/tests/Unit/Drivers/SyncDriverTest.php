<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Tests\Common\FakeThrowingListener;
use Lalaz\Events\Drivers\SyncDriver;

/**
 * Unit tests for SyncDriver
 *
 * Tests the SyncDriver which handles synchronous event processing
 * with priority-based listener execution.
 */
final class SyncDriverTest extends EventsUnitTestCase
{
    private SyncDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new SyncDriver();
    }

    #[Test]
    public function it_returns_sync_as_name(): void
    {
        $this->assertSame('sync', $this->driver->getName());
    }

    #[Test]
    public function it_is_always_available(): void
    {
        $this->assertTrue($this->driver->isAvailable());
    }

    #[Test]
    public function it_adds_a_callable_listener(): void
    {
        $listener = fn($data) => null;
        $this->driver->addListener('user.created', $listener);

        $this->assertTrue($this->driver->hasListeners('user.created'));
        $this->assertContains($listener, $this->driver->getListeners('user.created'));
    }

    #[Test]
    public function it_adds_multiple_listeners_to_same_event(): void
    {
        $listener1 = fn($data) => null;
        $listener2 = fn($data) => null;

        $this->driver->addListener('user.created', $listener1);
        $this->driver->addListener('user.created', $listener2);

        $listeners = $this->driver->getListeners('user.created');
        $this->assertCount(2, $listeners);
    }

    #[Test]
    public function it_adds_listeners_to_different_events(): void
    {
        $this->driver->addListener('user.created', fn($data) => null);
        $this->driver->addListener('user.deleted', fn($data) => null);

        $this->assertTrue($this->driver->hasListeners('user.created'));
        $this->assertTrue($this->driver->hasListeners('user.deleted'));
    }

    #[Test]
    public function it_returns_false_for_event_without_listeners(): void
    {
        $this->assertFalse($this->driver->hasListeners('nonexistent'));
    }

    #[Test]
    public function it_returns_true_for_event_with_listeners(): void
    {
        $this->driver->addListener('user.created', fn($data) => null);
        $this->assertTrue($this->driver->hasListeners('user.created'));
    }

    #[Test]
    public function it_returns_empty_array_for_event_without_listeners(): void
    {
        $this->assertSame([], $this->driver->getListeners('nonexistent'));
    }

    #[Test]
    public function it_returns_listeners_sorted_by_priority(): void
    {
        $low = fn($data) => 'low';
        $medium = fn($data) => 'medium';
        $high = fn($data) => 'high';

        $this->driver->addListener('test', $low, 0);
        $this->driver->addListener('test', $high, 10);
        $this->driver->addListener('test', $medium, 5);

        $listeners = $this->driver->getListeners('test');

        $this->assertSame($high, $listeners[0]);
        $this->assertSame($medium, $listeners[1]);
        $this->assertSame($low, $listeners[2]);
    }

    #[Test]
    public function it_removes_a_specific_listener(): void
    {
        $listener1 = fn($data) => null;
        $listener2 = fn($data) => null;

        $this->driver->addListener('test', $listener1);
        $this->driver->addListener('test', $listener2);

        $this->driver->removeListener('test', $listener1);

        $this->assertNotContains($listener1, $this->driver->getListeners('test'));
        $this->assertContains($listener2, $this->driver->getListeners('test'));
    }

    #[Test]
    public function it_removes_all_listeners_when_no_specific_listener_provided(): void
    {
        $this->driver->addListener('test', fn($data) => null);
        $this->driver->addListener('test', fn($data) => null);

        $this->driver->removeListener('test');

        $this->assertFalse($this->driver->hasListeners('test'));
    }

    #[Test]
    public function it_does_nothing_for_nonexistent_event(): void
    {
        $this->driver->removeListener('nonexistent');
        $this->assertFalse($this->driver->hasListeners('nonexistent'));
    }

    #[Test]
    public function it_executes_callable_listeners_with_data(): void
    {
        $received = null;
        $listener = function ($data) use (&$received) {
            $received = $data;
        };

        $this->driver->addListener('test', $listener);
        $this->driver->publish('test', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_executes_multiple_listeners_in_priority_order(): void
    {
        $order = [];

        $this->driver->addListener('test', function () use (&$order) {
            $order[] = 'low';
        }, 0);

        $this->driver->addListener('test', function () use (&$order) {
            $order[] = 'high';
        }, 10);

        $this->driver->addListener('test', function () use (&$order) {
            $order[] = 'medium';
        }, 5);

        $this->driver->publish('test', []);

        $this->assertSame(['high', 'medium', 'low'], $order);
    }

    #[Test]
    public function it_does_nothing_for_event_without_listeners(): void
    {
        $this->driver->publish('nonexistent', ['data']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_continues_execution_after_listener_error_by_default(): void
    {
        $executed = [];

        $this->driver->addListener('test', function () use (&$executed) {
            $executed[] = 'first';
            throw new \Exception('Test error');
        }, 10);

        $this->driver->addListener('test', function () use (&$executed) {
            $executed[] = 'second';
        }, 0);

        $this->driver->publish('test', []);

        $this->assertSame(['first', 'second'], $executed);
    }

    #[Test]
    public function it_stops_execution_on_error_when_stop_on_error_option_is_true(): void
    {
        $executed = [];

        $this->driver->addListener('test', function () use (&$executed) {
            $executed[] = 'first';
            throw new \Exception('Test error');
        }, 10);

        $this->driver->addListener('test', function () use (&$executed) {
            $executed[] = 'second';
        }, 0);

        $this->expectException(\Exception::class);
        $this->driver->publish('test', [], ['stop_on_error' => true]);

        $this->assertSame(['first'], $executed);
    }

    #[Test]
    public function it_executes_event_listener_instances(): void
    {
        $listener = new FakeEventListener(['test']);

        $this->driver->addListener('test', $listener);
        $this->driver->publish('test', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $listener->getLastEvent());
    }
}
