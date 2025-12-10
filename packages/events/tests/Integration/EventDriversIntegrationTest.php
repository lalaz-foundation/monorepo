<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsIntegrationTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Tests\Common\FakeMultiEventListener;
use Lalaz\Events\Tests\Common\FakeThrowingListener;
use Lalaz\Events\EventHub;
use Lalaz\Events\EventManager;
use Lalaz\Events\Events;
use Lalaz\Events\EventListener;
use Lalaz\Events\Drivers\SyncDriver;
use Lalaz\Events\Drivers\NullDriver;
use Lalaz\Events\Drivers\QueueDriver;

/**
 * Integration tests for Event Drivers.
 *
 * Tests the complete driver flow including:
 * - SyncDriver listener resolution and execution
 * - NullDriver recording and verification
 * - QueueDriver configuration and availability
 * - Driver switching and fallback scenarios
 *
 * @package lalaz/events
 */
final class EventDriversIntegrationTest extends EventsIntegrationTestCase
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
    // SyncDriver Integration Tests
    // =========================================================================

    #[Test]
    public function sync_driver_resolves_class_listeners_with_container(): void
    {
        $instances = [];
        $resolver = new \Lalaz\Events\ListenerResolver(function (string $class) use (&$instances) {
            $instance = new $class(['test.resolve']);
            $instances[] = $instance;
            return $instance;
        });

        $driver = new SyncDriver(resolver: $resolver);
        $driver->addListener('test.resolve', FakeEventListener::class);
        $driver->addListener('test.resolve', FakeEventListener::class);

        $driver->publish('test.resolve', ['key' => 'value']);

        $this->assertCount(2, $instances);
        foreach ($instances as $instance) {
            $this->assertSame(['key' => 'value'], $instance->getLastEvent());
        }
    }

    #[Test]
    public function sync_driver_handles_mixed_listener_types(): void
    {
        $results = [];

        $resolver = new \Lalaz\Events\ListenerResolver(function (string $class) use (&$results) {
            $listener = new FakeEventListener(['test.mixed']);
            return $listener;
        });

        $driver = new SyncDriver(resolver: $resolver);

        // Closure listener
        $driver->addListener('test.mixed', function ($data) use (&$results) {
            $results[] = ['type' => 'closure', 'data' => $data];
        }, 30);

        // EventListener instance
        $listener = new FakeEventListener(['test.mixed']);
        $driver->addListener('test.mixed', $listener, 20);

        // Class string listener
        $driver->addListener('test.mixed', FakeEventListener::class, 10);

        $driver->publish('test.mixed', ['event' => 'data']);

        // Closure adds to results, listener instance receives event, class is resolved
        $this->assertCount(1, $results);
        $this->assertSame('closure', $results[0]['type']);
        $this->assertSame(['event' => 'data'], $listener->getLastEvent());
    }

    #[Test]
    public function sync_driver_maintains_listener_state_across_events(): void
    {
        $driver = new SyncDriver();
        $listener = new FakeEventListener(['event.a', 'event.b']);

        $driver->addListener('event.a', $listener);
        $driver->addListener('event.b', $listener);

        $driver->publish('event.a', ['first' => true]);
        $driver->publish('event.b', ['second' => true]);

        // Last event should be event.b
        $this->assertSame(['second' => true], $listener->getLastEvent());
    }

    #[Test]
    public function sync_driver_continues_on_listener_error(): void
    {
        $driver = new SyncDriver();
        $executed = [];

        $driver->addListener('test.error', function () use (&$executed) {
            $executed[] = 'before_error';
            throw new \RuntimeException('Listener failed');
        }, 100);

        $driver->addListener('test.error', function () use (&$executed) {
            $executed[] = 'after_error';
        }, 0);

        // Should not throw
        $driver->publish('test.error', []);

        $this->assertContains('before_error', $executed);
        $this->assertContains('after_error', $executed);
    }

    #[Test]
    public function sync_driver_stops_on_error_when_configured(): void
    {
        $driver = new SyncDriver();
        $executed = [];

        $driver->addListener('test.stop', function () use (&$executed) {
            $executed[] = 'first';
            throw new \RuntimeException('Stop here');
        }, 100);

        $driver->addListener('test.stop', function () use (&$executed) {
            $executed[] = 'second';
        }, 0);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stop here');

        $driver->publish('test.stop', [], ['stop_on_error' => true]);
    }

    #[Test]
    public function sync_driver_resolver_can_be_changed_at_runtime(): void
    {
        $driver = new SyncDriver();
        $resolverCalled = false;

        $driver->setResolver(function (string $class) use (&$resolverCalled) {
            $resolverCalled = true;
            return new $class(['test']);
        });

        $driver->addListener('test.runtime', FakeEventListener::class);
        $driver->publish('test.runtime', []);

        $this->assertTrue($resolverCalled);
    }

    // =========================================================================
    // NullDriver Integration Tests
    // =========================================================================

    #[Test]
    public function null_driver_records_complete_event_lifecycle(): void
    {
        $driver = $this->recordingDriver();

        // Publish multiple events
        $driver->publish('user.created', ['id' => 1, 'name' => 'John']);
        $driver->publish('user.updated', ['id' => 1, 'name' => 'John Doe']);
        $driver->publish('user.created', ['id' => 2, 'name' => 'Jane']);
        $driver->publish('user.deleted', ['id' => 1]);

        // Verify counts
        $this->assertSame(4, $driver->count());
        $this->assertTrue($driver->wasPublished('user.created'));
        $this->assertTrue($driver->wasPublished('user.updated'));
        $this->assertTrue($driver->wasPublished('user.deleted'));
        $this->assertFalse($driver->wasPublished('user.banned'));

        // Verify specific event publications
        $createdEvents = $driver->getPublicationsOf('user.created');
        $this->assertCount(2, $createdEvents);

        // getPublicationsOf returns filtered array, access by values
        $createdValues = array_values($createdEvents);
        $this->assertSame('John', $createdValues[0]['data']['name']);
        $this->assertSame('Jane', $createdValues[1]['data']['name']);
    }

    #[Test]
    public function null_driver_can_verify_event_payload(): void
    {
        $driver = $this->recordingDriver();

        $driver->publish('order.placed', [
            'order_id' => 'ORD-123',
            'total' => 99.99,
            'items' => [
                ['sku' => 'PROD-1', 'qty' => 2],
                ['sku' => 'PROD-2', 'qty' => 1],
            ],
        ]);

        $publications = $driver->getPublicationsOf('order.placed');
        $this->assertCount(1, $publications);

        $payload = $publications[0]['data'];
        $this->assertSame('ORD-123', $payload['order_id']);
        $this->assertSame(99.99, $payload['total']);
        $this->assertCount(2, $payload['items']);
    }

    #[Test]
    public function null_driver_clears_recorded_events(): void
    {
        $driver = $this->recordingDriver();

        $driver->publish('test.event', ['data' => 1]);
        $driver->publish('test.event', ['data' => 2]);

        $this->assertSame(2, $driver->count());

        $driver->clear();

        $this->assertSame(0, $driver->count());
        $this->assertFalse($driver->wasPublished('test.event'));
    }

    #[Test]
    public function null_driver_records_options_with_events(): void
    {
        $driver = $this->recordingDriver();

        $driver->publish('background.task', ['task' => 'process'], ['priority' => 'high', 'delay' => 30]);

        $publications = $driver->getPublicationsOf('background.task');
        $this->assertSame('high', $publications[0]['options']['priority']);
        $this->assertSame(30, $publications[0]['options']['delay']);
    }

    #[Test]
    public function null_driver_without_recording_ignores_events(): void
    {
        $driver = new NullDriver(recordEvents: false);

        $driver->publish('test.ignored', ['data' => 'value']);

        $this->assertSame(0, $driver->count());
        $this->assertFalse($driver->wasPublished('test.ignored'));
    }

    // =========================================================================
    // QueueDriver Integration Tests
    // =========================================================================

    #[Test]
    public function queue_driver_reports_availability_status(): void
    {
        $driver = new QueueDriver();

        // Without queue manager, driver should not be available
        $this->assertFalse($driver->isAvailable());
    }

    #[Test]
    public function queue_driver_preserves_configuration(): void
    {
        $driver = new QueueDriver(
            queue: 'custom-events',
            priority: 5,
            delay: 60
        );

        $this->assertSame('custom-events', $driver->getQueue());
        $this->assertSame(5, $driver->getPriority());
        $this->assertSame(60, $driver->getDelay());
        $this->assertSame('queue', $driver->getName());
    }

    #[Test]
    public function queue_driver_accepts_various_configurations(): void
    {
        $defaultDriver = new QueueDriver();
        $this->assertSame('events', $defaultDriver->getQueue());
        $this->assertSame(9, $defaultDriver->getPriority());
        $this->assertNull($defaultDriver->getDelay());

        $customDriver = new QueueDriver('notifications', 1, 120);
        $this->assertSame('notifications', $customDriver->getQueue());
        $this->assertSame(1, $customDriver->getPriority());
        $this->assertSame(120, $customDriver->getDelay());
    }

    // =========================================================================
    // Driver Switching and Fallback Tests
    // =========================================================================

    #[Test]
    public function event_hub_falls_back_to_sync_when_async_unavailable(): void
    {
        $unavailableDriver = new class implements \Lalaz\Events\Contracts\EventDriverInterface {
            public function publish(string $event, mixed $data, array $options = []): void {}
            public function isAvailable(): bool { return false; }
            public function getName(): string { return 'unavailable'; }
        };

        $hub = new EventHub($unavailableDriver);
        $received = null;

        $hub->register('test.fallback', function ($data) use (&$received) {
            $received = $data;
        });

        // Should fall back to sync execution
        $hub->trigger('test.fallback', ['fallback' => true]);

        $this->assertSame(['fallback' => true], $received);
    }

    #[Test]
    public function event_hub_uses_async_when_available(): void
    {
        $asyncDriver = $this->recordingDriver();
        $hub = new EventHub($asyncDriver);

        $syncReceived = null;
        $hub->register('test.async', function ($data) use (&$syncReceived) {
            $syncReceived = $data;
        });

        // Async trigger should go to driver
        $hub->trigger('test.async', ['async' => true]);

        $this->assertTrue($asyncDriver->wasPublished('test.async'));
        $this->assertNull($syncReceived); // Sync listener not called
    }

    #[Test]
    public function event_hub_respects_async_disabled_flag(): void
    {
        $asyncDriver = $this->recordingDriver();
        $hub = new EventHub($asyncDriver);
        $hub->setAsyncEnabled(false);

        $syncReceived = null;
        $hub->register('test.disabled', function ($data) use (&$syncReceived) {
            $syncReceived = $data;
        });

        // With async disabled, should execute sync
        $hub->trigger('test.disabled', ['sync' => true]);

        $this->assertFalse($asyncDriver->wasPublished('test.disabled'));
        $this->assertSame(['sync' => true], $syncReceived);
    }

    #[Test]
    public function event_hub_can_switch_async_driver_at_runtime(): void
    {
        $driver1 = $this->recordingDriver();
        $driver2 = $this->recordingDriver();

        $hub = new EventHub($driver1);

        $hub->trigger('test.driver1', []);
        $this->assertTrue($driver1->wasPublished('test.driver1'));
        $this->assertFalse($driver2->wasPublished('test.driver1'));

        $hub->setAsyncDriver($driver2);

        $hub->trigger('test.driver2', []);
        $this->assertFalse($driver1->wasPublished('test.driver2'));
        $this->assertTrue($driver2->wasPublished('test.driver2'));
    }

    #[Test]
    public function event_manager_provides_direct_sync_driver_access(): void
    {
        $manager = new EventManager();

        $syncDriver = $manager->getSyncDriver();
        $this->assertInstanceOf(SyncDriver::class, $syncDriver);

        // Can add listeners directly to driver
        $received = null;
        $syncDriver->addListener('direct.access', function ($data) use (&$received) {
            $received = $data;
        });

        $manager->triggerSync('direct.access', ['direct' => true]);
        $this->assertSame(['direct' => true], $received);
    }
}
