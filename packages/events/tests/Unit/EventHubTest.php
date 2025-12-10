<?php declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\EventHub;
use Lalaz\Events\Contracts\EventDispatcherInterface;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for EventHub class.
 *
 * Tests the main event dispatcher functionality including:
 * - Listener registration and retrieval
 * - Event triggering (sync and async)
 * - Driver configuration
 * - Priority ordering
 *
 * @package lalaz/events
 */
class EventHubTest extends EventsUnitTestCase
{
    private EventHub $hub;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hub = $this->createEventHub();
    }

    // =========================================================================
    // Interface Implementation Tests
    // =========================================================================

    #[Test]
    public function it_implements_event_dispatcher_interface(): void
    {
        $this->assertInstanceOf(EventDispatcherInterface::class, $this->hub);
    }

    // =========================================================================
    // Constructor Tests
    // =========================================================================

    #[Test]
    public function it_creates_instance_without_async_driver(): void
    {
        $hub = $this->createEventHub();
        $this->assertNull($hub->getAsyncDriver());
    }

    #[Test]
    public function it_creates_instance_with_async_driver(): void
    {
        $driver = $this->createNullDriver();
        $hub = $this->createEventHub($driver);

        $this->assertSame($driver, $hub->getAsyncDriver());
    }

    // =========================================================================
    // Listener Registration Tests
    // =========================================================================

    #[Test]
    public function it_registers_a_callable_listener(): void
    {
        $listener = fn($data) => null;
        $this->hub->register('user.created', $listener);

        $this->assertHasListeners($this->hub, 'user.created');
    }

    #[Test]
    public function it_registers_a_string_class_listener(): void
    {
        $this->hub->register('user.created', FakeEventListener::class);

        $this->assertHasListeners($this->hub, 'user.created');
    }

    #[Test]
    public function it_registers_multiple_listeners_for_same_event(): void
    {
        $this->hub->register('user.created', fn($data) => 'first');
        $this->hub->register('user.created', fn($data) => 'second');

        $this->assertCount(2, $this->hub->getListeners('user.created'));
    }

    #[Test]
    public function it_accepts_priority_parameter(): void
    {
        $low = fn($data) => 'low';
        $high = fn($data) => 'high';

        $this->hub->register('test', $low, 0);
        $this->hub->register('test', $high, 10);

        $listeners = $this->hub->getListeners('test');
        $this->assertSame($high, $listeners[0]);
        $this->assertSame($low, $listeners[1]);
    }

    // =========================================================================
    // Forget Tests
    // =========================================================================

    #[Test]
    public function it_forgets_a_specific_listener(): void
    {
        $listener = fn($data) => null;
        $this->hub->register('test', $listener);

        $this->assertHasListeners($this->hub, 'test');

        $this->hub->forget('test', $listener);

        $this->assertNoListeners($this->hub, 'test');
    }

    #[Test]
    public function it_forgets_all_listeners_when_no_specific_listener_provided(): void
    {
        $this->hub->register('test', fn($data) => 'first');
        $this->hub->register('test', fn($data) => 'second');

        $this->hub->forget('test');

        $this->assertNoListeners($this->hub, 'test');
    }

    // =========================================================================
    // Has Listeners Tests
    // =========================================================================

    #[Test]
    public function it_returns_false_for_event_without_listeners(): void
    {
        $this->assertNoListeners($this->hub, 'nonexistent');
    }

    #[Test]
    public function it_returns_true_for_event_with_listeners(): void
    {
        $this->hub->register('user.created', fn($data) => null);
        $this->assertHasListeners($this->hub, 'user.created');
    }

    // =========================================================================
    // Get Listeners Tests
    // =========================================================================

    #[Test]
    public function it_returns_empty_array_for_event_without_listeners(): void
    {
        $this->assertSame([], $this->hub->getListeners('nonexistent'));
    }

    #[Test]
    public function it_returns_registered_listeners(): void
    {
        $listener = fn($data) => null;
        $this->hub->register('test', $listener);

        $this->assertContains($listener, $this->hub->getListeners('test'));
    }

    // =========================================================================
    // Trigger Tests
    // =========================================================================

    #[Test]
    public function it_falls_back_to_sync_when_no_async_driver(): void
    {
        $received = null;
        $this->hub->register('test', function ($data) use (&$received) {
            $received = $data;
        });

        $this->hub->trigger('test', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_uses_async_driver_when_available(): void
    {
        $asyncDriver = $this->createNullDriver(recordEvents: true);
        $hub = $this->createEventHub($asyncDriver);

        $hub->register('user.created', fn($data) => null);
        $hub->trigger('user.created', ['user_id' => 1]);

        $this->assertEventPublished($asyncDriver, 'user.created');
    }

    #[Test]
    public function it_falls_back_to_sync_when_async_is_disabled(): void
    {
        $asyncDriver = $this->createNullDriver(recordEvents: true);
        $hub = $this->createEventHub($asyncDriver);
        $hub->setAsyncEnabled(false);

        $received = null;
        $hub->register('test', function ($data) use (&$received) {
            $received = $data;
        });

        $hub->trigger('test', ['key' => 'value']);

        $this->assertEventNotPublished($asyncDriver, 'test');
        $this->assertSame(['key' => 'value'], $received);
    }

    // =========================================================================
    // Trigger Sync Tests
    // =========================================================================

    #[Test]
    public function it_executes_listeners_synchronously(): void
    {
        $received = null;
        $this->hub->register('test', function ($data) use (&$received) {
            $received = $data;
        });

        $this->hub->triggerSync('test', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_ignores_async_driver_on_sync_trigger(): void
    {
        $asyncDriver = $this->createNullDriver(recordEvents: true);
        $hub = $this->createEventHub($asyncDriver);

        $received = null;
        $hub->register('test', function ($data) use (&$received) {
            $received = $data;
        });

        $hub->triggerSync('test', ['key' => 'value']);

        $this->assertEventNotPublished($asyncDriver, 'test');
        $this->assertSame(['key' => 'value'], $received);
    }

    // =========================================================================
    // Async Configuration Tests
    // =========================================================================

    #[Test]
    public function it_can_enable_async(): void
    {
        $this->hub->setAsyncEnabled(true);
        $this->assertTrue($this->hub->isAsyncEnabled());
    }

    #[Test]
    public function it_can_disable_async(): void
    {
        $this->hub->setAsyncEnabled(false);
        $this->assertFalse($this->hub->isAsyncEnabled());
    }

    #[Test]
    public function it_can_set_async_driver(): void
    {
        $driver = $this->createNullDriver();
        $this->hub->setAsyncDriver($driver);

        $this->assertSame($driver, $this->hub->getAsyncDriver());
    }

    #[Test]
    public function it_can_clear_async_driver(): void
    {
        $this->hub->setAsyncDriver($this->createNullDriver());
        $this->hub->setAsyncDriver(null);

        $this->assertNull($this->hub->getAsyncDriver());
    }

    // =========================================================================
    // Factory Method Tests
    // =========================================================================

    #[Test]
    public function it_creates_hub_with_queue_driver(): void
    {
        $hub = EventHub::withQueueDriver('custom-queue', 5);

        $driver = $hub->getAsyncDriver();
        $this->assertNotNull($driver);
        $this->assertSame('queue', $driver->getName());
    }

    #[Test]
    public function it_creates_sync_only_hub(): void
    {
        $hub = EventHub::syncOnly();

        $this->assertFalse($hub->isAsyncEnabled());
    }

    // =========================================================================
    // Event Listener Instance Tests
    // =========================================================================

    #[Test]
    public function it_executes_event_listener_instances(): void
    {
        $listener = $this->createFakeListener(['test.event']);
        $this->hub->register('test.event', $listener);

        $this->hub->triggerSync('test.event', ['message' => 'hello']);

        $this->assertSame(['message' => 'hello'], $listener->getLastEvent());
    }
}
