<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Contracts;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Contracts\EventDispatcherInterface;
use Lalaz\Events\Contracts\EventPublisherInterface;
use Lalaz\Events\Contracts\EventRegistrarInterface;
use Lalaz\Events\Contracts\EventIntrospectionInterface;
use Lalaz\Events\EventHub;
use Lalaz\Events\EventManager;

/**
 * Tests for Interface Segregation Principle compliance
 *
 * Verifies that the segregated interfaces are properly implemented
 * and that clients can depend on smaller interfaces
 */
final class InterfaceSegregationTest extends EventsUnitTestCase
{
    #[Test]
    public function event_hub_implements_event_dispatcher_interface(): void
    {
        $hub = new EventHub();

        $this->assertInstanceOf(EventDispatcherInterface::class, $hub);
    }

    #[Test]
    public function event_hub_implements_event_publisher_interface(): void
    {
        $hub = new EventHub();

        $this->assertInstanceOf(EventPublisherInterface::class, $hub);
    }

    #[Test]
    public function event_hub_implements_event_registrar_interface(): void
    {
        $hub = new EventHub();

        $this->assertInstanceOf(EventRegistrarInterface::class, $hub);
    }

    #[Test]
    public function event_hub_implements_event_introspection_interface(): void
    {
        $hub = new EventHub();

        $this->assertInstanceOf(EventIntrospectionInterface::class, $hub);
    }

    #[Test]
    public function event_manager_implements_event_dispatcher_interface(): void
    {
        $manager = new EventManager();

        $this->assertInstanceOf(EventDispatcherInterface::class, $manager);
    }

    #[Test]
    public function event_manager_implements_event_publisher_interface(): void
    {
        $manager = new EventManager();

        $this->assertInstanceOf(EventPublisherInterface::class, $manager);
    }

    #[Test]
    public function event_manager_implements_event_registrar_interface(): void
    {
        $manager = new EventManager();

        $this->assertInstanceOf(EventRegistrarInterface::class, $manager);
    }

    #[Test]
    public function event_manager_implements_event_introspection_interface(): void
    {
        $manager = new EventManager();

        $this->assertInstanceOf(EventIntrospectionInterface::class, $manager);
    }

    #[Test]
    public function client_can_depend_only_on_publisher(): void
    {
        $hub = new EventHub();

        // Simulate a client that only needs publishing capability
        $this->publishEvent($hub, 'test.event', ['data' => 'value']);

        $this->assertTrue(true); // No exception means success
    }

    #[Test]
    public function client_can_depend_only_on_registrar(): void
    {
        $hub = new EventHub();

        // Simulate a client that only needs registration capability
        $this->registerListener($hub, 'test.event', fn() => null);

        $this->assertTrue(true);
    }

    #[Test]
    public function client_can_depend_only_on_introspection(): void
    {
        $hub = new EventHub();

        // Simulate a client that only needs introspection capability
        $result = $this->checkHasListeners($hub, 'test.event');

        $this->assertFalse($result);
    }

    /**
     * Helper method that only depends on EventPublisherInterface
     */
    private function publishEvent(EventPublisherInterface $publisher, string $event, mixed $data): void
    {
        $publisher->triggerSync($event, $data);
    }

    /**
     * Helper method that only depends on EventRegistrarInterface
     */
    private function registerListener(EventRegistrarInterface $registrar, string $event, callable $listener): void
    {
        $registrar->register($event, $listener);
    }

    /**
     * Helper method that only depends on EventIntrospectionInterface
     */
    private function checkHasListeners(EventIntrospectionInterface $introspector, string $event): bool
    {
        return $introspector->hasListeners($event);
    }
}
