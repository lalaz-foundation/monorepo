<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\EventManager;
use Lalaz\Events\Drivers\SyncDriver;
use Lalaz\Events\Contracts\EventDispatcherInterface;

/**
 * Unit tests for EventManager
 *
 * Tests the EventManager class which provides an alternative
 * API for event management with additional configuration options.
 */
final class EventManagerTest extends EventsUnitTestCase
{
    private EventManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new EventManager();
    }

    #[Test]
    public function it_implements_event_dispatcher_interface(): void
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, $this->manager);
    }

    #[Test]
    public function it_creates_instance_without_async_driver(): void
    {
        $manager = new EventManager();
        $this->assertNull($manager->getAsyncDriver());
    }

    #[Test]
    public function it_creates_instance_with_async_driver(): void
    {
        $driver = $this->createNullDriver();
        $manager = new EventManager($driver);

        $this->assertSame($driver, $manager->getAsyncDriver());
    }

    #[Test]
    public function it_has_async_enabled_by_default(): void
    {
        $manager = new EventManager();
        $this->assertTrue($manager->isAsyncEnabled());
    }

    #[Test]
    public function it_can_disable_async_on_construction(): void
    {
        $manager = new EventManager(null, false);
        $this->assertFalse($manager->isAsyncEnabled());
    }

    #[Test]
    public function it_registers_a_callable_listener(): void
    {
        $listener = fn($data) => null;
        $this->manager->register('user.created', $listener);

        $this->assertHasListeners($this->manager, 'user.created');
    }

    #[Test]
    public function it_registers_an_event_listener_instance(): void
    {
        $listener = new FakeEventListener(['test.event']);
        $this->manager->register('test.event', $listener);

        $this->assertHasListeners($this->manager, 'test.event');
    }

    #[Test]
    public function it_registers_a_string_class_listener(): void
    {
        $this->manager->register('user.created', FakeEventListener::class);

        $this->assertHasListeners($this->manager, 'user.created');
    }

    #[Test]
    public function it_registers_multiple_listeners_for_same_event(): void
    {
        $this->manager->register('user.created', fn($data) => 'first');
        $this->manager->register('user.created', fn($data) => 'second');

        $this->assertCount(2, $this->manager->getListeners('user.created'));
    }

    #[Test]
    public function it_accepts_priority_parameter(): void
    {
        $low = fn($data) => 'low';
        $high = fn($data) => 'high';

        $this->manager->register('test', $low, 0);
        $this->manager->register('test', $high, 10);

        $listeners = $this->manager->getListeners('test');
        $this->assertSame($high, $listeners[0]);
        $this->assertSame($low, $listeners[1]);
    }

    #[Test]
    public function it_forgets_a_specific_listener(): void
    {
        $listener = fn($data) => null;
        $this->manager->register('test', $listener);

        $this->assertHasListeners($this->manager, 'test');

        $this->manager->forget('test', $listener);

        $this->assertNoListeners($this->manager, 'test');
    }

    #[Test]
    public function it_forgets_all_listeners_when_no_specific_listener_provided(): void
    {
        $this->manager->register('test', fn($data) => 'first');
        $this->manager->register('test', fn($data) => 'second');

        $this->manager->forget('test');

        $this->assertNoListeners($this->manager, 'test');
    }

    #[Test]
    public function it_returns_false_for_event_without_listeners(): void
    {
        $this->assertFalse($this->manager->hasListeners('nonexistent'));
    }

    #[Test]
    public function it_returns_true_for_event_with_listeners(): void
    {
        $this->manager->register('user.created', fn($data) => null);
        $this->assertTrue($this->manager->hasListeners('user.created'));
    }

    #[Test]
    public function it_returns_empty_array_for_event_without_listeners(): void
    {
        $this->assertSame([], $this->manager->getListeners('nonexistent'));
    }

    #[Test]
    public function it_returns_registered_listeners(): void
    {
        $listener = fn($data) => null;
        $this->manager->register('test', $listener);

        $this->assertContains($listener, $this->manager->getListeners('test'));
    }

    #[Test]
    public function it_falls_back_to_sync_when_no_async_driver(): void
    {
        $received = null;
        $this->manager->register('test', function ($data) use (&$received) {
            $received = $data;
        });

        $this->manager->trigger('test', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_uses_async_driver_when_available(): void
    {
        $asyncDriver = $this->createNullDriver(recordEvents: true);
        $manager = new EventManager($asyncDriver);

        $manager->register('user.created', fn($data) => null);
        $manager->trigger('user.created', ['user_id' => 1]);

        $this->assertEventPublished($asyncDriver, 'user.created');
    }

    #[Test]
    public function it_falls_back_to_sync_when_async_is_disabled(): void
    {
        $asyncDriver = $this->createNullDriver(recordEvents: true);
        $manager = new EventManager($asyncDriver);
        $manager->setAsyncEnabled(false);

        $received = null;
        $manager->register('test', function ($data) use (&$received) {
            $received = $data;
        });

        $manager->trigger('test', ['key' => 'value']);

        $this->assertEventNotPublished($asyncDriver, 'test');
        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_executes_listeners_synchronously(): void
    {
        $received = null;
        $this->manager->register('test', function ($data) use (&$received) {
            $received = $data;
        });

        $this->manager->triggerSync('test', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_ignores_async_driver_on_sync_trigger(): void
    {
        $asyncDriver = $this->createNullDriver(recordEvents: true);
        $manager = new EventManager($asyncDriver);

        $received = null;
        $manager->register('test', function ($data) use (&$received) {
            $received = $data;
        });

        $manager->triggerSync('test', ['key' => 'value']);

        $this->assertEventNotPublished($asyncDriver, 'test');
        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_can_enable_async(): void
    {
        $this->manager->setAsyncEnabled(true);
        $this->assertTrue($this->manager->isAsyncEnabled());
    }

    #[Test]
    public function it_can_disable_async(): void
    {
        $this->manager->setAsyncEnabled(false);
        $this->assertFalse($this->manager->isAsyncEnabled());
    }

    #[Test]
    public function it_can_set_async_driver(): void
    {
        $driver = $this->createNullDriver();
        $this->manager->setAsyncDriver($driver);

        $this->assertSame($driver, $this->manager->getAsyncDriver());
    }

    #[Test]
    public function it_can_clear_async_driver(): void
    {
        $this->manager->setAsyncDriver($this->createNullDriver());
        $this->manager->setAsyncDriver(null);

        $this->assertNull($this->manager->getAsyncDriver());
    }

    #[Test]
    public function it_returns_sync_driver_instance(): void
    {
        $syncDriver = $this->manager->getSyncDriver();

        $this->assertInstanceOf(SyncDriver::class, $syncDriver);
    }

    #[Test]
    public function it_creates_manager_with_queue_driver(): void
    {
        $manager = EventManager::withQueueDriver('custom-queue', 5);

        $driver = $manager->getAsyncDriver();
        $this->assertNotNull($driver);
        $this->assertSame('queue', $driver->getName());
    }

    #[Test]
    public function it_creates_sync_only_manager(): void
    {
        $manager = EventManager::syncOnly();

        $this->assertFalse($manager->isAsyncEnabled());
        $this->assertNull($manager->getAsyncDriver());
    }

    #[Test]
    public function it_creates_manager_for_testing(): void
    {
        $manager = EventManager::forTesting(true);

        $driver = $manager->getAsyncDriver();
        $this->assertNotNull($driver);
        $this->assertSame('null', $driver->getName());
    }

    #[Test]
    public function it_executes_event_listener_instances(): void
    {
        $listener = new FakeEventListener(['test.event']);
        $this->manager->register('test.event', $listener);

        $this->manager->triggerSync('test.event', ['message' => 'hello']);

        $this->assertSame(['message' => 'hello'], $listener->getLastEvent());
    }

    #[Test]
    public function it_executes_listeners_in_priority_order(): void
    {
        $order = [];

        $this->manager->register('test', function () use (&$order) {
            $order[] = 'low';
        }, 0);

        $this->manager->register('test', function () use (&$order) {
            $order[] = 'high';
        }, 10);

        $this->manager->register('test', function () use (&$order) {
            $order[] = 'medium';
        }, 5);

        $this->manager->triggerSync('test', []);

        $this->assertSame(['high', 'medium', 'low'], $order);
    }
}
