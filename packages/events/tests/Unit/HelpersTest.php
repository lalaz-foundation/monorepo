<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Events;
use Lalaz\Events\EventHub;

/**
 * Unit tests for helper functions
 *
 * Tests the global helper functions defined in helpers.php
 */
final class HelpersTest extends EventsUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Events::setInstance(EventHub::syncOnly());
    }

    protected function tearDown(): void
    {
        Events::setInstance(null);
        parent::tearDown();
    }

    #[Test]
    public function dispatch_triggers_event_asynchronously(): void
    {
        $received = null;
        Events::register('test.dispatch', function ($data) use (&$received) {
            $received = $data;
        });

        dispatch('test.dispatch', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function dispatch_works_with_null_payload(): void
    {
        $called = false;
        Events::register('test.null', function ($data) use (&$called) {
            $called = true;
        });

        dispatch('test.null');

        $this->assertTrue($called);
    }

    #[Test]
    public function dispatch_sync_triggers_event_synchronously(): void
    {
        $received = null;
        Events::register('test.sync', function ($data) use (&$received) {
            $received = $data;
        });

        dispatchSync('test.sync', ['sync' => true]);

        $this->assertSame(['sync' => true], $received);
    }

    #[Test]
    public function dispatch_sync_works_with_null_payload(): void
    {
        $called = false;
        Events::register('test.sync.null', function ($data) use (&$called) {
            $called = true;
        });

        dispatchSync('test.sync.null');

        $this->assertTrue($called);
    }

    #[Test]
    public function event_helper_triggers_event(): void
    {
        $received = null;
        Events::register('test.event', function ($data) use (&$received) {
            $received = $data;
        });

        event('test.event', ['helper' => 'event']);

        $this->assertSame(['helper' => 'event'], $received);
    }

    #[Test]
    public function event_helper_works_with_null_payload(): void
    {
        $called = false;
        Events::register('test.event.null', function ($data) use (&$called) {
            $called = true;
        });

        event('test.event.null');

        $this->assertTrue($called);
    }

    #[Test]
    public function listen_registers_callable_listener(): void
    {
        $received = null;

        listen('test.listen', function ($data) use (&$received) {
            $received = $data;
        });

        Events::triggerSync('test.listen', ['listened' => true]);

        $this->assertSame(['listened' => true], $received);
    }

    #[Test]
    public function listen_registers_class_listener(): void
    {
        listen('test.class', FakeEventListener::class);

        $this->assertTrue(Events::hasListeners('test.class'));
    }

    #[Test]
    public function listen_accepts_priority(): void
    {
        $order = [];

        listen('test.priority', function () use (&$order) {
            $order[] = 'low';
        }, 0);

        listen('test.priority', function () use (&$order) {
            $order[] = 'high';
        }, 10);

        Events::triggerSync('test.priority', []);

        $this->assertSame(['high', 'low'], $order);
    }

    #[Test]
    public function forget_event_removes_specific_listener(): void
    {
        $listener = function ($data) {};

        listen('test.forget', $listener);
        $this->assertTrue(Events::hasListeners('test.forget'));

        forget_event('test.forget', $listener);
        $this->assertFalse(Events::hasListeners('test.forget'));
    }

    #[Test]
    public function forget_event_removes_all_listeners(): void
    {
        listen('test.forget.all', function () {});
        listen('test.forget.all', function () {});

        $this->assertTrue(Events::hasListeners('test.forget.all'));

        forget_event('test.forget.all');

        $this->assertFalse(Events::hasListeners('test.forget.all'));
    }

    #[Test]
    public function has_listeners_returns_true_when_listeners_exist(): void
    {
        listen('test.has', function () {});

        $this->assertTrue(has_listeners('test.has'));
    }

    #[Test]
    public function has_listeners_returns_false_when_no_listeners(): void
    {
        $this->assertFalse(has_listeners('nonexistent.event'));
    }
}
