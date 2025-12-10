<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Common;

use Lalaz\Testing\Integration\IntegrationTestCase;
use Lalaz\Events\EventHub;
use Lalaz\Events\EventManager;
use Lalaz\Events\Events;
use Lalaz\Events\Drivers\NullDriver;
use Lalaz\Events\Drivers\SyncDriver;

/**
 * Base test case for Events package integration tests.
 *
 * Extends IntegrationTestCase from lalaz/testing to provide
 * container bootstrapping plus event-specific helpers.
 *
 * @package lalaz/events
 */
abstract class EventsIntegrationTestCase extends IntegrationTestCase
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
     *
     * @param NullDriver|null $asyncDriver Optional async driver
     */
    protected function createEventHub(?NullDriver $asyncDriver = null): EventHub
    {
        return new EventHub($asyncDriver);
    }

    /**
     * Create an EventHub with a custom resolver for class listeners.
     * Uses EventManager internally since EventHub doesn't expose resolver.
     *
     * @param callable $resolver The resolver callable
     * @return EventManager Returns EventManager which has the same interface
     */
    protected function createEventHubWithResolver(callable $resolver): EventManager
    {
        $manager = new EventManager();
        $manager->getSyncDriver()->setResolver($resolver);
        return $manager;
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
     * Create a recording NullDriver (alias for createNullDriver with recordEvents: true).
     */
    protected function recordingDriver(): NullDriver
    {
        return new NullDriver(recordEvents: true);
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
     *
     * @param array<int, string> $events
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
    // Setup Events Facade
    // =========================================================================

    /**
     * Setup the Events facade with a fresh hub.
     */
    protected function setUpEventsFacade(?EventHub $hub = null): EventHub
    {
        $hub = $hub ?? $this->createSyncOnlyHub();
        Events::setInstance($hub);
        return $hub;
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
     * Assert that specific events were triggered a given number of times.
     *
     * @param NullDriver $driver
     * @param array<int, string> $eventNames
     * @param int $expectedCount
     * @param string $message
     */
    protected function assertEventsTriggered(NullDriver $driver, array $eventNames, int $expectedCount, string $message = ''): void
    {
        foreach ($eventNames as $eventName) {
            $this->assertEventPublished($driver, $eventName);
            $publications = $driver->getPublicationsOf($eventName);
            $this->assertCount(
                $expectedCount,
                $publications,
                $message ?: "Expected {$expectedCount} triggers for '{$eventName}'"
            );
        }
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
     * Assert event payload matches expected data.
     *
     * @param NullDriver $driver
     * @param string $eventName
     * @param array<string, mixed> $expectedData
     * @param string $message
     */
    protected function assertEventPayload(NullDriver $driver, string $eventName, array $expectedData, string $message = ''): void
    {
        $publications = $driver->getPublicationsOf($eventName);
        $this->assertNotEmpty($publications, "No events found for '{$eventName}'");

        $lastPublication = end($publications);
        $this->assertEquals(
            $expectedData,
            $lastPublication['data'],
            $message ?: "Event payload does not match expected data"
        );
    }
}
