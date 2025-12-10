<?php declare(strict_types=1);

namespace Lalaz\Events\Tests\Common;

use Lalaz\Testing\Unit\UnitTestCase;
use Lalaz\Events\EventHub;
use Lalaz\Events\EventManager;
use Lalaz\Events\Events;
use Lalaz\Events\EventListener;
use Lalaz\Events\Drivers\NullDriver;
use Lalaz\Events\Drivers\SyncDriver;

/**
 * Base test case for Events package unit tests.
 *
 * Extends UnitTestCase from lalaz/testing to provide
 * common utilities plus event-specific helpers like
 * fake listeners and driver factories.
 *
 * @package lalaz/events
 */
abstract class EventsUnitTestCase extends UnitTestCase
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Events::setInstance(null);
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        Events::setInstance(null);
        parent::tearDown();
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create a new EventHub instance.
     */
    protected function createEventHub(?NullDriver $asyncDriver = null): EventHub
    {
        return new EventHub($asyncDriver);
    }

    /**
     * Create a sync-only EventHub instance.
     */
    protected function createSyncOnlyHub(): EventHub
    {
        return EventHub::syncOnly();
    }

    /**
     * Create an EventHub with recording NullDriver for testing.
     */
    protected function createTestingHub(): EventHub
    {
        return new EventHub(new NullDriver(recordEvents: true));
    }

    /**
     * Create a new EventManager instance.
     */
    protected function createEventManager(?NullDriver $asyncDriver = null, bool $asyncEnabled = true): EventManager
    {
        return new EventManager($asyncDriver, $asyncEnabled);
    }

    /**
     * Create a NullDriver for testing with optional recording.
     */
    protected function createNullDriver(bool $recordEvents = true): NullDriver
    {
        return new NullDriver($recordEvents);
    }

    /**
     * Create a SyncDriver with optional resolver.
     */
    protected function createSyncDriver(?callable $resolver = null): SyncDriver
    {
        return new SyncDriver($resolver);
    }

    /**
     * Create a fake event listener for testing.
     */
    protected function createFakeListener(array $events = ['test.event']): FakeEventListener
    {
        return new FakeEventListener($events);
    }

    /**
     * Create a callable listener that records received events.
     *
     * @return array{listener: callable, getReceived: callable}
     */
    protected function createRecordingListener(): array
    {
        $received = [];

        return [
            'listener' => function (mixed $data) use (&$received) {
                $received[] = $data;
            },
            'getReceived' => function () use (&$received) {
                return $received;
            },
        ];
    }

    // =========================================================================
    // Event Assertions
    // =========================================================================

    /**
     * Assert that an event was published to a NullDriver.
     */
    protected function assertEventPublished(NullDriver $driver, string $eventName, string $message = ''): void
    {
        $this->assertTrue(
            $driver->wasPublished($eventName),
            $message ?: "Event '{$eventName}' should have been published"
        );
    }

    /**
     * Assert that an event was not published to a NullDriver.
     */
    protected function assertEventNotPublished(NullDriver $driver, string $eventName, string $message = ''): void
    {
        $this->assertFalse(
            $driver->wasPublished($eventName),
            $message ?: "Event '{$eventName}' should not have been published"
        );
    }

    /**
     * Assert that a specific number of events were published.
     */
    protected function assertEventCount(NullDriver $driver, int $expected, string $message = ''): void
    {
        $this->assertSame(
            $expected,
            $driver->count(),
            $message ?: "Expected {$expected} events to be published"
        );
    }

    /**
     * Assert that an EventHub has listeners for an event.
     */
    protected function assertHasListeners(EventHub|EventManager $dispatcher, string $eventName, string $message = ''): void
    {
        $this->assertTrue(
            $dispatcher->hasListeners($eventName),
            $message ?: "Dispatcher should have listeners for '{$eventName}'"
        );
    }

    /**
     * Assert that an EventHub has no listeners for an event.
     */
    protected function assertNoListeners(EventHub|EventManager $dispatcher, string $eventName, string $message = ''): void
    {
        $this->assertFalse(
            $dispatcher->hasListeners($eventName),
            $message ?: "Dispatcher should not have listeners for '{$eventName}'"
        );
    }

    /**
     * Assert that listeners are in expected priority order.
     */
    protected function assertListenerOrder(EventHub|EventManager $dispatcher, string $eventName, array $expectedOrder, string $message = ''): void
    {
        $listeners = $dispatcher->getListeners($eventName);
        $actualOrder = array_values($listeners);

        $this->assertEquals(
            $expectedOrder,
            $actualOrder,
            $message ?: 'Listeners are not in expected order'
        );
    }
}
