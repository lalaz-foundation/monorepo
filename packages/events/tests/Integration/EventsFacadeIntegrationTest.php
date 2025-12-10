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

/**
 * Integration tests for Events Facade.
 *
 * Tests the complete facade flow including:
 * - Static API wrapper functionality
 * - Instance switching at runtime
 * - Complete listener registration/trigger flow
 * - Facade with different dispatcher implementations
 *
 * @package lalaz/events
 */
final class EventsFacadeIntegrationTest extends EventsIntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Events::setInstance(null);
    }

    protected function tearDown(): void
    {
        Events::setInstance(null);
        parent::tearDown();
    }

    // =========================================================================
    // Instance Management Tests
    // =========================================================================

    #[Test]
    public function facade_works_with_event_hub_instance(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $received = [];
        Events::register('test.hub', function ($data) use (&$received) {
            $received[] = $data;
        });

        Events::trigger('test.hub', ['source' => 'hub']);

        $this->assertCount(1, $received);
        $this->assertSame('hub', $received[0]['source']);
    }

    #[Test]
    public function facade_works_with_event_manager_instance(): void
    {
        $manager = EventManager::syncOnly();
        Events::setInstance($manager);

        $received = [];
        Events::register('test.manager', function ($data) use (&$received) {
            $received[] = $data;
        });

        Events::trigger('test.manager', ['source' => 'manager']);

        $this->assertCount(1, $received);
        $this->assertSame('manager', $received[0]['source']);
    }

    #[Test]
    public function facade_allows_instance_replacement(): void
    {
        $hub1 = EventHub::syncOnly();
        $hub2 = EventHub::syncOnly();

        Events::setInstance($hub1);
        Events::register('test.replace', fn() => null);
        $this->assertTrue(Events::hasListeners('test.replace'));

        Events::setInstance($hub2);
        // New instance doesn't have the old listener
        $this->assertFalse(Events::hasListeners('test.replace'));
    }

    #[Test]
    public function facade_handles_null_instance_gracefully(): void
    {
        Events::setInstance(null);

        // These should not throw
        Events::trigger('test.null', ['data' => 'value']);
        Events::triggerSync('test.null', ['data' => 'value']);
        Events::register('test.null', fn() => null);
        Events::forget('test.null');

        $this->assertFalse(Events::hasListeners('test.null'));
        $this->assertSame([], Events::getListeners('test.null'));
    }

    // =========================================================================
    // Complete Workflow Tests
    // =========================================================================

    #[Test]
    public function facade_supports_complete_event_workflow(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $workflow = [];

        // 1. Register listeners
        Events::register('order.created', function ($data) use (&$workflow) {
            $workflow[] = "order_created:{$data['order_id']}";
        }, 100);

        Events::register('order.created', function ($data) use (&$workflow) {
            $workflow[] = "notification_sent:{$data['order_id']}";
        }, 50);

        Events::register('order.shipped', function ($data) use (&$workflow) {
            $workflow[] = "order_shipped:{$data['order_id']}";
        });

        // 2. Check listeners exist
        $this->assertTrue(Events::hasListeners('order.created'));
        $this->assertTrue(Events::hasListeners('order.shipped'));
        $this->assertFalse(Events::hasListeners('order.cancelled'));

        // 3. Trigger events
        Events::triggerSync('order.created', ['order_id' => 'ORD-001']);
        Events::triggerSync('order.shipped', ['order_id' => 'ORD-001']);

        // 4. Verify workflow
        $this->assertSame([
            'order_created:ORD-001',
            'notification_sent:ORD-001',
            'order_shipped:ORD-001',
        ], $workflow);

        // 5. Forget specific listener
        $listeners = Events::getListeners('order.created');
        Events::forget('order.created', $listeners[0]);
        $this->assertCount(1, Events::getListeners('order.created'));

        // 6. Forget all listeners
        Events::forget('order.shipped');
        $this->assertFalse(Events::hasListeners('order.shipped'));
    }

    #[Test]
    public function facade_works_with_event_listener_classes(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $listener = new FakeEventListener(['user.registered']);
        Events::register('user.registered', $listener);

        Events::triggerSync('user.registered', [
            'user_id' => 42,
            'email' => 'test@example.com',
        ]);

        $lastEvent = $listener->getLastEvent();
        $this->assertSame(42, $lastEvent['user_id']);
        $this->assertSame('test@example.com', $lastEvent['email']);
    }

    #[Test]
    public function facade_works_with_multi_event_listeners(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $listener = new FakeMultiEventListener();

        foreach ($listener->subscribers() as $eventName) {
            Events::register($eventName, $listener);
        }

        Events::triggerSync('user.created', ['action' => 'created']);
        Events::triggerSync('user.updated', ['action' => 'updated']);
        Events::triggerSync('user.deleted', ['action' => 'deleted']);

        $events = $listener->getReceivedEvents();
        $this->assertCount(3, $events);
    }

    // =========================================================================
    // Priority and Ordering Tests
    // =========================================================================

    #[Test]
    public function facade_respects_listener_priority(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $order = [];

        Events::register('test.priority', function () use (&$order) {
            $order[] = 'low';
        }, 0);

        Events::register('test.priority', function () use (&$order) {
            $order[] = 'high';
        }, 100);

        Events::register('test.priority', function () use (&$order) {
            $order[] = 'medium';
        }, 50);

        Events::register('test.priority', function () use (&$order) {
            $order[] = 'very_high';
        }, 200);

        Events::triggerSync('test.priority', []);

        $this->assertSame(['very_high', 'high', 'medium', 'low'], $order);
    }

    #[Test]
    public function facade_returns_listeners_in_priority_order(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $low = fn() => 'low';
        $high = fn() => 'high';
        $medium = fn() => 'medium';

        Events::register('test.order', $low, 10);
        Events::register('test.order', $high, 100);
        Events::register('test.order', $medium, 50);

        $listeners = Events::getListeners('test.order');

        $this->assertSame($high, $listeners[0]);
        $this->assertSame($medium, $listeners[1]);
        $this->assertSame($low, $listeners[2]);
    }

    // =========================================================================
    // Async vs Sync Behavior Tests
    // =========================================================================

    #[Test]
    public function facade_trigger_uses_async_driver_when_available(): void
    {
        $asyncDriver = $this->recordingDriver();
        $hub = new EventHub($asyncDriver);
        Events::setInstance($hub);

        $syncExecuted = false;
        Events::register('test.async', function () use (&$syncExecuted) {
            $syncExecuted = true;
        });

        Events::trigger('test.async', ['data' => 'value']);

        // Event went to async driver, sync listener not called
        $this->assertTrue($asyncDriver->wasPublished('test.async'));
        $this->assertFalse($syncExecuted);
    }

    #[Test]
    public function facade_trigger_sync_bypasses_async_driver(): void
    {
        $asyncDriver = $this->recordingDriver();
        $hub = new EventHub($asyncDriver);
        Events::setInstance($hub);

        $syncExecuted = false;
        Events::register('test.sync', function () use (&$syncExecuted) {
            $syncExecuted = true;
        });

        Events::triggerSync('test.sync', ['data' => 'value']);

        // Sync listener called, async driver not used
        $this->assertTrue($syncExecuted);
        $this->assertFalse($asyncDriver->wasPublished('test.sync'));
    }

    // =========================================================================
    // Edge Cases Tests
    // =========================================================================

    #[Test]
    public function facade_handles_special_characters_in_event_names(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $received = [];

        Events::register('app:user.created@v2', function ($data) use (&$received) {
            $received['v2'] = $data;
        });

        Events::register('module::event', function ($data) use (&$received) {
            $received['module'] = $data;
        });

        Events::triggerSync('app:user.created@v2', ['version' => 2]);
        Events::triggerSync('module::event', ['source' => 'module']);

        $this->assertSame(2, $received['v2']['version']);
        $this->assertSame('module', $received['module']['source']);
    }

    #[Test]
    public function facade_handles_complex_payloads(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $received = null;
        Events::register('test.complex', function ($data) use (&$received) {
            $received = $data;
        });

        $complexPayload = [
            'user' => (object) ['id' => 1, 'name' => 'John'],
            'items' => [
                ['sku' => 'A', 'qty' => 2],
                ['sku' => 'B', 'qty' => 1],
            ],
            'metadata' => [
                'timestamp' => time(),
                'nested' => ['deep' => ['value' => true]],
            ],
            'callback' => fn() => 'result',
        ];

        Events::triggerSync('test.complex', $complexPayload);

        $this->assertEquals($complexPayload['user']->id, $received['user']->id);
        $this->assertCount(2, $received['items']);
        $this->assertTrue($received['metadata']['nested']['deep']['value']);
        $this->assertIsCallable($received['callback']);
    }

    #[Test]
    public function facade_handles_empty_event_name(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $received = null;
        Events::register('', function ($data) use (&$received) {
            $received = $data;
        });

        Events::triggerSync('', ['empty' => 'name']);

        $this->assertSame(['empty' => 'name'], $received);
    }

    #[Test]
    public function facade_handles_null_payload(): void
    {
        $hub = EventHub::syncOnly();
        Events::setInstance($hub);

        $received = 'not_called';
        Events::register('test.null', function ($data) use (&$received) {
            $received = $data;
        });

        Events::triggerSync('test.null', null);

        $this->assertNull($received);
    }
}
