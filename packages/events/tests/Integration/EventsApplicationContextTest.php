<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Integration;

use Lalaz\Events\Contracts\EventDispatcherInterface;
use Lalaz\Events\Contracts\EventPublisherInterface;
use Lalaz\Events\EventHub;
use Lalaz\Events\EventJob;
use Lalaz\Events\Events;
use Lalaz\Events\EventServiceProvider;
use Lalaz\Testing\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for Events with Application context
 *
 * Tests the branches that depend on Application::context() being available.
 * Uses TestApplication from lalaz/testing package to bootstrap the framework.
 */
final class EventsApplicationContextTest extends IntegrationTestCase
{
    /**
     * Register the Events service provider.
     */
    protected function getPackageProviders(): array
    {
        return [
            EventServiceProvider::class,
        ];
    }

    /**
     * Configuration for tests.
     */
    protected function getPackageConfig(): array
    {
        return [
            'base_path' => dirname(__DIR__, 2),
            'debug' => true,
        ];
    }

    protected function tearDown(): void
    {
        Events::setInstance(null);
        EventJob::resetTestState();
        parent::tearDown();
    }

    #[Test]
    public function events_facade_get_instance_returns_hub_from_application_context(): void
    {
        // The TestApplication should have set up Application::context()
        // and EventServiceProvider should have registered EventHub

        $instance = Events::getInstance();

        $this->assertNotNull($instance);
        $this->assertInstanceOf(EventDispatcherInterface::class, $instance);
    }

    #[Test]
    public function events_facade_can_register_and_trigger_with_application_context(): void
    {
        $triggered = false;

        Events::register('test.context.event', function () use (&$triggered) {
            $triggered = true;
        });

        Events::triggerSync('test.context.event', []);

        $this->assertTrue($triggered);
    }

    #[Test]
    public function event_hub_is_registered_as_singleton_in_container(): void
    {
        $hub1 = $this->resolve(EventHub::class);
        $hub2 = $this->resolve(EventHub::class);

        $this->assertSame($hub1, $hub2);
    }

    #[Test]
    public function event_dispatcher_interface_resolves_to_event_hub(): void
    {
        $dispatcher = $this->resolve(EventDispatcherInterface::class);

        $this->assertInstanceOf(EventHub::class, $dispatcher);
    }

    #[Test]
    public function events_facade_has_listeners_works_with_application_context(): void
    {
        Events::register('has.listeners.test', fn() => null);

        $this->assertTrue(Events::hasListeners('has.listeners.test'));
        $this->assertFalse(Events::hasListeners('no.listeners.event'));
    }

    #[Test]
    public function events_facade_get_listeners_returns_registered_listeners(): void
    {
        $listener = fn() => 'result';
        Events::register('get.listeners.test', $listener);

        $listeners = Events::getListeners('get.listeners.test');

        $this->assertCount(1, $listeners);
    }

    #[Test]
    public function events_facade_forget_removes_listener_with_application_context(): void
    {
        $listener = fn() => null;
        Events::register('forget.test', $listener);

        $this->assertTrue(Events::hasListeners('forget.test'));

        Events::forget('forget.test');

        $this->assertFalse(Events::hasListeners('forget.test'));
    }

    #[Test]
    public function event_job_resolves_publisher_from_application_context(): void
    {
        // Clear any test state
        EventJob::resetTestState();

        // Set up Application::context()->events() by calling setEvents on the Application
        /** @var EventHub $hub */
        $hub = $this->resolve(EventHub::class);

        if (class_exists(\Lalaz\Runtime\Application::class)) {
            \Lalaz\Runtime\Application::context()->setEvents($hub);
        }

        $job = new EventJob();

        // Verify handle works with Application context
        $triggered = false;

        $hub->register('job.context.test', function () use (&$triggered) {
            $triggered = true;
        });

        $job->handle([
            'event_name' => 'job.context.test',
            'event_data' => json_encode(['key' => 'value']),
        ]);

        $this->assertTrue($triggered);
    }

    #[Test]
    public function application_context_provides_events_instance(): void
    {
        // Access the events through Application context
        if (class_exists(\Lalaz\Runtime\Application::class)) {
            /** @var EventHub $hub */
            $hub = $this->resolve(EventHub::class);

            // Must explicitly set events on Application context
            \Lalaz\Runtime\Application::context()->setEvents($hub);

            $context = \Lalaz\Runtime\Application::context();
            $events = $context->events();

            $this->assertInstanceOf(EventPublisherInterface::class, $events);
        } else {
            $this->markTestSkipped('Framework Application class not available');
        }
    }

    #[Test]
    public function events_work_correctly_through_full_application_lifecycle(): void
    {
        $eventData = [];

        // Register multiple listeners
        Events::register('lifecycle.test', function ($data) use (&$eventData) {
            $eventData[] = ['first', $data];
        });

        Events::register('lifecycle.test', function ($data) use (&$eventData) {
            $eventData[] = ['second', $data];
        });

        // Trigger event
        Events::triggerSync('lifecycle.test', ['payload' => 'test']);

        // Verify both listeners were called
        $this->assertCount(2, $eventData);
        $this->assertSame('first', $eventData[0][0]);
        $this->assertSame('second', $eventData[1][0]);
    }

    #[Test]
    public function refreshing_application_resets_events_state(): void
    {
        Events::register('refresh.test', fn() => null);
        $this->assertTrue(Events::hasListeners('refresh.test'));

        // Refresh the application
        $this->refreshApplication();

        // After refresh, Events::setInstance(null) should have been called in tearDown
        // and the new application should have a fresh EventHub
        Events::setInstance(null);

        // Get new instance from container
        $this->assertFalse(Events::hasListeners('refresh.test'));
    }
}
