<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Events;
use Lalaz\Events\EventHub;

/**
 * Unit tests for Events Facade
 *
 * Tests the static Events facade which provides a convenient
 * API for accessing the event system globally.
 */
final class EventsTest extends EventsUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Always set an instance to avoid Application::context() fallback
        Events::setInstance(new EventHub());
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        Events::setInstance(null);
        parent::tearDown();
    }

    #[Test]
    public function it_sets_the_dispatcher_instance(): void
    {
        $hub = new EventHub();
        Events::setInstance($hub);

        $this->assertSame($hub, Events::getInstance());
    }

    #[Test]
    public function it_registers_listener_when_instance_is_set(): void
    {
        Events::register('user.created', fn($data) => null);

        $this->assertTrue(Events::hasListeners('user.created'));
    }

    #[Test]
    public function it_accepts_priority_on_register(): void
    {
        $low = fn($data) => 'low';
        $high = fn($data) => 'high';

        Events::register('test', $low, 0);
        Events::register('test', $high, 10);

        $listeners = Events::getListeners('test');
        $this->assertSame($high, $listeners[0]);
        $this->assertSame($low, $listeners[1]);
    }

    #[Test]
    public function it_accepts_event_listener_class(): void
    {
        Events::register('facade.test', FakeEventListener::class);

        $this->assertTrue(Events::hasListeners('facade.test'));
    }

    #[Test]
    public function it_forgets_specific_listener(): void
    {
        $listener = fn($data) => null;
        Events::register('test', $listener);

        $this->assertTrue(Events::hasListeners('test'));

        Events::forget('test', $listener);

        $this->assertFalse(Events::hasListeners('test'));
    }

    #[Test]
    public function it_forgets_all_listeners_when_no_listener_specified(): void
    {
        Events::register('test', fn($data) => 'first');
        Events::register('test', fn($data) => 'second');

        Events::forget('test');

        $this->assertFalse(Events::hasListeners('test'));
    }

    #[Test]
    public function it_triggers_event_to_listeners(): void
    {
        Events::setInstance(EventHub::syncOnly());

        $received = null;
        Events::register('test', function ($data) use (&$received) {
            $received = $data;
        });

        Events::trigger('test', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_triggers_event_synchronously(): void
    {
        $received = null;
        Events::register('test', function ($data) use (&$received) {
            $received = $data;
        });

        Events::triggerSync('test', ['message' => 'hello']);

        $this->assertSame(['message' => 'hello'], $received);
    }

    #[Test]
    public function it_returns_false_for_event_without_listeners(): void
    {
        $this->assertFalse(Events::hasListeners('nonexistent'));
    }

    #[Test]
    public function it_returns_true_for_event_with_listeners(): void
    {
        Events::register('user.created', fn($data) => null);

        $this->assertTrue(Events::hasListeners('user.created'));
    }

    #[Test]
    public function it_returns_empty_array_for_event_without_listeners(): void
    {
        $this->assertSame([], Events::getListeners('nonexistent'));
    }

    #[Test]
    public function it_returns_registered_listeners(): void
    {
        $listener = fn($data) => null;
        Events::register('test', $listener);

        $listeners = Events::getListeners('test');
        $this->assertContains($listener, $listeners);
    }

    #[Test]
    public function it_registers_multiple_events(): void
    {
        Events::register('user.created', fn($data) => null);
        Events::register('user.updated', fn($data) => null);
        Events::register('user.deleted', fn($data) => null);

        $this->assertTrue(Events::hasListeners('user.created'));
        $this->assertTrue(Events::hasListeners('user.updated'));
        $this->assertTrue(Events::hasListeners('user.deleted'));
    }

    #[Test]
    public function it_can_replace_instance(): void
    {
        $hub1 = new EventHub();
        $hub2 = new EventHub();

        Events::setInstance($hub1);
        Events::register('test', fn($data) => null);

        Events::setInstance($hub2);

        // New instance should not have the listener
        $this->assertFalse(Events::hasListeners('test'));
    }
}
