<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsIntegrationTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Tests\Common\FakeMultiEventListener;
use Lalaz\Events\EventHub;
use Lalaz\Events\EventManager;
use Lalaz\Events\Events;
use Lalaz\Events\EventListener;
use Lalaz\Events\Drivers\SyncDriver;

/**
 * Integration tests for the Event System
 *
 * Tests the complete event flow including EventHub, EventManager,
 * Events facade, and various driver configurations working together.
 */
final class EventSystemIntegrationTest extends EventsIntegrationTestCase
{
    /** @var array<int, mixed> */
    private static array $processedOrders = [];

    /** @var array<int, mixed> */
    private static array $shippedOrders = [];

    /** @var array<int, mixed> */
    private static array $multiEvents = [];

    protected function setUp(): void
    {
        parent::setUp();
        Events::setInstance(null);
        self::$processedOrders = [];
        self::$shippedOrders = [];
        self::$multiEvents = [];
    }

    protected function tearDown(): void
    {
        Events::setInstance(null);
        parent::tearDown();
    }

    #[Test]
    public function it_handles_complete_event_flow_with_event_hub(): void
    {
        $hub = $this->createEventHub();
        $callbackReceived = null;

        // Register multiple listeners
        $listener = new FakeEventListener(['order.created']);
        $hub->register('order.created', $listener);
        $hub->register('order.created', function ($data) use (&$callbackReceived) {
            $callbackReceived = $data;
        });

        // Trigger event
        $orderData = ['order_id' => 123, 'total' => 99.99];
        $hub->triggerSync('order.created', $orderData);

        // Assert both listeners received the event
        $this->assertSame($orderData, $listener->getLastEvent());
        $this->assertSame($orderData, $callbackReceived);
    }

    #[Test]
    public function it_handles_complete_event_flow_with_event_manager(): void
    {
        $manager = new EventManager();
        $listener = new FakeEventListener(['order.shipped']);

        $manager->register('order.shipped', $listener);

        $shipmentData = ['order_id' => 456, 'tracking' => 'ABC123'];
        $manager->triggerSync('order.shipped', $shipmentData);

        $this->assertSame($shipmentData, $listener->getLastEvent());
    }

    #[Test]
    public function it_integrates_with_events_facade(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $received = [];
        Events::register('notification.sent', function ($data) use (&$received) {
            $received[] = $data;
        });

        Events::trigger('notification.sent', ['type' => 'email', 'to' => 'user@example.com']);
        Events::trigger('notification.sent', ['type' => 'sms', 'to' => '+1234567890']);

        $this->assertCount(2, $received);
        $this->assertSame('email', $received[0]['type']);
        $this->assertSame('sms', $received[1]['type']);
    }

    #[Test]
    public function it_records_async_events_for_later_processing(): void
    {
        $driver = $this->recordingDriver();
        $hub = new EventHub($driver);

        $hub->register('background.job', fn($data) => null);

        // Trigger async event
        $hub->trigger('background.job', ['job' => 'process_images']);
        $hub->trigger('background.job', ['job' => 'send_emails']);

        // Verify events were recorded
        $this->assertSame(2, $driver->count());
        $this->assertEventsTriggered($driver, ['background.job'], 2);

        $publications = $driver->getPublicationsOf('background.job');
        $this->assertSame('process_images', $publications[0]['data']['job']);
        $this->assertSame('send_emails', $publications[1]['data']['job']);
    }

    #[Test]
    public function it_executes_listeners_in_priority_order(): void
    {
        $hub = $this->createEventHub();
        $executionOrder = [];

        // Register various listener types with different priorities
        $hub->register('test.priority', function () use (&$executionOrder) {
            $executionOrder[] = 'callback_low';
        }, 0);

        $hub->register('test.priority', function () use (&$executionOrder) {
            $executionOrder[] = 'callback_high';
        }, 100);

        $hub->register('test.priority', function () use (&$executionOrder) {
            $executionOrder[] = 'callback_medium';
        }, 50);

        $hub->triggerSync('test.priority', []);

        $this->assertSame(['callback_high', 'callback_medium', 'callback_low'], $executionOrder);
    }

    #[Test]
    public function it_delivers_to_multi_event_listener(): void
    {
        $hub = $this->createEventHub();
        $listener = new FakeMultiEventListener();

        // Register for all subscribed events
        foreach ($listener->subscribers() as $eventName) {
            $hub->register($eventName, $listener);
        }

        // Trigger various events
        $hub->triggerSync('user.created', ['id' => 1, 'action' => 'created']);
        $hub->triggerSync('user.updated', ['id' => 1, 'action' => 'updated']);

        $events = $listener->getReceivedEvents();
        $this->assertCount(2, $events);
        $this->assertSame('created', $events[0]['action']);
        $this->assertSame('updated', $events[1]['action']);
    }

    #[Test]
    public function it_allows_hub_and_manager_to_coexist(): void
    {
        $hub = $this->createEventHub();
        $manager = new EventManager();

        $hubReceived = null;
        $managerReceived = null;

        $hub->register('test.event', function ($data) use (&$hubReceived) {
            $hubReceived = $data;
        });

        $manager->register('test.event', function ($data) use (&$managerReceived) {
            $managerReceived = $data;
        });

        $hub->triggerSync('test.event', ['source' => 'hub']);
        $manager->triggerSync('test.event', ['source' => 'manager']);

        $this->assertSame(['source' => 'hub'], $hubReceived);
        $this->assertSame(['source' => 'manager'], $managerReceived);
    }

    #[Test]
    public function it_forgets_listeners_and_prevents_execution(): void
    {
        $hub = $this->createEventHub();
        $executed = false;

        $listener = function () use (&$executed) {
            $executed = true;
        };

        $hub->register('test.forget', $listener);
        $hub->forget('test.forget', $listener);

        $hub->triggerSync('test.forget', []);

        $this->assertFalse($executed);
    }

    #[Test]
    public function it_resolves_class_listeners_with_custom_resolver(): void
    {
        $resolved = [];
        $resolver = new \Lalaz\Events\ListenerResolver(function (string $class) use (&$resolved) {
            $resolved[] = $class;
            return new $class(['test.resolve']);
        });

        $syncDriver = new SyncDriver(resolver: $resolver);
        $syncDriver->addListener('test.resolve', FakeEventListener::class);
        $syncDriver->publish('test.resolve', ['test' => 'data']);

        $this->assertContains(FakeEventListener::class, $resolved);
    }

    #[Test]
    public function it_continues_execution_on_error_by_default(): void
    {
        $hub = $this->createEventHub();
        $executed = [];

        $hub->register('test.error', function () use (&$executed) {
            $executed[] = 'first';
            throw new \RuntimeException('Test error');
        }, 10);

        $hub->register('test.error', function () use (&$executed) {
            $executed[] = 'second';
        }, 0);

        $hub->triggerSync('test.error', []);

        // Both listeners should have executed
        $this->assertContains('first', $executed);
        $this->assertContains('second', $executed);
    }

    #[Test]
    public function it_switches_between_sync_and_async_modes(): void
    {
        $asyncDriver = $this->recordingDriver();
        $hub = new EventHub($asyncDriver);

        $syncReceived = null;
        $hub->register('test.mode', function ($data) use (&$syncReceived) {
            $syncReceived = $data;
        });

        // Async mode (default) - goes to driver
        $hub->trigger('test.mode', ['mode' => 'async']);
        $this->assertTrue($asyncDriver->wasPublished('test.mode'));
        $this->assertNull($syncReceived); // Sync listener not called

        // Sync mode - executes inline
        $hub->triggerSync('test.mode', ['mode' => 'sync']);
        $this->assertSame(['mode' => 'sync'], $syncReceived);
    }

    #[Test]
    public function it_creates_properly_configured_instances_via_factory_methods(): void
    {
        // EventHub::syncOnly
        $syncHub = EventHub::syncOnly();
        $this->assertFalse($syncHub->isAsyncEnabled());

        // EventHub::withQueueDriver
        $queueHub = EventHub::withQueueDriver('my-queue', 5);
        $driver = $queueHub->getAsyncDriver();
        $this->assertNotNull($driver);
        $this->assertSame('queue', $driver->getName());

        // EventManager::syncOnly
        $syncManager = EventManager::syncOnly();
        $this->assertFalse($syncManager->isAsyncEnabled());

        // EventManager::forTesting
        $testManager = EventManager::forTesting();
        $this->assertSame('null', $testManager->getAsyncDriver()->getName());
    }
}
