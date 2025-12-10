<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\EventHub;
use Lalaz\Events\ListenerResolver;
use Lalaz\Events\Drivers\QueueDriver;

/**
 * Tests for static factory methods in Events package
 */
final class FactoryMethodsTest extends EventsUnitTestCase
{
    #[Test]
    public function event_hub_with_queue_driver_creates_hub_with_queue_driver(): void
    {
        $hub = EventHub::withQueueDriver();

        $this->assertInstanceOf(EventHub::class, $hub);
        $this->assertInstanceOf(QueueDriver::class, $hub->getAsyncDriver());
    }

    #[Test]
    public function event_hub_with_queue_driver_uses_custom_queue_name(): void
    {
        $hub = EventHub::withQueueDriver('custom-events');

        $driver = $hub->getAsyncDriver();
        $this->assertInstanceOf(QueueDriver::class, $driver);

        // Verify queue name via reflection
        $reflection = new \ReflectionClass($driver);
        $queueProperty = $reflection->getProperty('queue');
        $queueProperty->setAccessible(true);

        $this->assertSame('custom-events', $queueProperty->getValue($driver));
    }

    #[Test]
    public function event_hub_with_queue_driver_uses_custom_priority(): void
    {
        $hub = EventHub::withQueueDriver('events', 5);

        $driver = $hub->getAsyncDriver();
        $this->assertInstanceOf(QueueDriver::class, $driver);

        // Verify priority via reflection
        $reflection = new \ReflectionClass($driver);
        $priorityProperty = $reflection->getProperty('priority');
        $priorityProperty->setAccessible(true);

        $this->assertSame(5, $priorityProperty->getValue($driver));
    }

    #[Test]
    public function event_hub_sync_only_creates_hub_with_async_disabled(): void
    {
        $hub = EventHub::syncOnly();

        $this->assertInstanceOf(EventHub::class, $hub);
        $this->assertFalse($hub->isAsyncEnabled());
        $this->assertNull($hub->getAsyncDriver());
    }

    #[Test]
    public function event_hub_sync_only_always_dispatches_synchronously(): void
    {
        $hub = EventHub::syncOnly();

        $triggered = false;
        $hub->register('test.event', function () use (&$triggered) {
            $triggered = true;
        });

        $hub->trigger('test.event', []);

        $this->assertTrue($triggered);
    }

    #[Test]
    public function listener_resolver_direct_creates_resolver_with_direct_instantiation(): void
    {
        $resolver = ListenerResolver::direct();

        $this->assertInstanceOf(ListenerResolver::class, $resolver);
    }

    #[Test]
    public function listener_resolver_direct_instantiates_classes_directly(): void
    {
        $resolver = ListenerResolver::direct();

        $instance = $resolver->resolve(SimpleTestListener::class);

        $this->assertInstanceOf(SimpleTestListener::class, $instance);
    }

    #[Test]
    public function listener_resolver_from_creates_resolver_with_custom_callable(): void
    {
        $customInstance = new SimpleTestListener();

        $resolver = ListenerResolver::from(fn(string $class) => $customInstance);

        $this->assertInstanceOf(ListenerResolver::class, $resolver);
        $this->assertSame($customInstance, $resolver->resolve(SimpleTestListener::class));
    }

    #[Test]
    public function listener_resolver_from_callable_receives_class_name(): void
    {
        $receivedClass = null;

        $resolver = ListenerResolver::from(function (string $class) use (&$receivedClass) {
            $receivedClass = $class;
            return new $class();
        });

        $resolver->resolve(SimpleTestListener::class);

        $this->assertSame(SimpleTestListener::class, $receivedClass);
    }
}

/**
 * Simple test listener for factory method tests
 */
class SimpleTestListener
{
    public function __invoke(mixed $data): void
    {
        // Do nothing
    }
}
