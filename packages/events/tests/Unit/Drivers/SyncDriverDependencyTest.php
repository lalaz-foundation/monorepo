<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Contracts\ListenerRegistryInterface;
use Lalaz\Events\Contracts\ListenerResolverInterface;
use Lalaz\Events\Drivers\SyncDriver;
use Lalaz\Events\ListenerRegistry;
use Lalaz\Events\ListenerResolver;

/**
 * Unit tests for SyncDriver dependency injection
 *
 * Tests SOLID compliance - DIP with injected dependencies
 */
final class SyncDriverDependencyTest extends EventsUnitTestCase
{
    #[Test]
    public function it_returns_default_registry_when_not_provided(): void
    {
        $driver = new SyncDriver();

        $registry = $driver->getRegistry();

        $this->assertInstanceOf(ListenerRegistryInterface::class, $registry);
        $this->assertInstanceOf(ListenerRegistry::class, $registry);
    }

    #[Test]
    public function it_returns_default_resolver_when_not_provided(): void
    {
        $driver = new SyncDriver();

        $resolver = $driver->getResolver();

        $this->assertInstanceOf(ListenerResolverInterface::class, $resolver);
        $this->assertInstanceOf(ListenerResolver::class, $resolver);
    }

    #[Test]
    public function it_accepts_custom_registry(): void
    {
        $customRegistry = new ListenerRegistry();

        $driver = new SyncDriver(registry: $customRegistry);

        $this->assertSame($customRegistry, $driver->getRegistry());
    }

    #[Test]
    public function it_accepts_custom_resolver(): void
    {
        $customResolver = ListenerResolver::direct();

        $driver = new SyncDriver(resolver: $customResolver);

        $this->assertSame($customResolver, $driver->getResolver());
    }

    #[Test]
    public function it_uses_injected_registry_for_listener_storage(): void
    {
        $registry = new ListenerRegistry();
        $driver = new SyncDriver(registry: $registry);

        $listener = fn($data) => null;
        $driver->addListener('test.event', $listener);

        // Registry should have the listener
        $this->assertTrue($registry->has('test.event'));
        $this->assertContains($listener, $registry->get('test.event'));
    }

    #[Test]
    public function it_uses_injected_resolver_for_class_resolution(): void
    {
        $customInstance = new FakeEventListener(['custom']);
        $resolverCalled = false;

        $resolver = new ListenerResolver(function (string $class) use ($customInstance, &$resolverCalled) {
            $resolverCalled = true;
            return $customInstance;
        });

        $driver = new SyncDriver(resolver: $resolver);
        $driver->addListener('test.resolve', FakeEventListener::class);
        $driver->publish('test.resolve', ['data' => 'value']);

        $this->assertTrue($resolverCalled);
        $this->assertSame(['data' => 'value'], $customInstance->getLastEvent());
    }

    #[Test]
    public function it_can_inject_both_registry_and_resolver(): void
    {
        $registry = new ListenerRegistry();
        $resolver = ListenerResolver::direct();

        $driver = new SyncDriver(
            registry: $registry,
            resolver: $resolver
        );

        $this->assertSame($registry, $driver->getRegistry());
        $this->assertSame($resolver, $driver->getResolver());
    }

    #[Test]
    public function set_resolver_creates_new_listener_resolver(): void
    {
        $driver = new SyncDriver();

        $originalResolver = $driver->getResolver();

        $customResolverCalled = false;
        $driver->setResolver(function (string $class) use (&$customResolverCalled) {
            $customResolverCalled = true;
            return new FakeEventListener(['test']);
        });

        $newResolver = $driver->getResolver();

        // Should be a new instance
        $this->assertNotSame($originalResolver, $newResolver);
        $this->assertInstanceOf(ListenerResolver::class, $newResolver);

        // Should use the new resolver
        $driver->addListener('test.setresolver', FakeEventListener::class);
        $driver->publish('test.setresolver', []);

        $this->assertTrue($customResolverCalled);
    }

    #[Test]
    public function it_shares_registry_state_between_operations(): void
    {
        $registry = new ListenerRegistry();
        $driver = new SyncDriver(registry: $registry);

        // Add listener via driver
        $driver->addListener('event.one', fn() => null);

        // Add listener directly to registry
        $registry->add('event.two', fn() => null, 0);

        // Both should be visible via driver
        $this->assertTrue($driver->hasListeners('event.one'));
        $this->assertTrue($driver->hasListeners('event.two'));
    }

    #[Test]
    public function it_handles_custom_registry_with_preloaded_listeners(): void
    {
        $registry = new ListenerRegistry();
        $executed = false;

        $registry->add('preloaded.event', function ($data) use (&$executed) {
            $executed = true;
        }, 0);

        $driver = new SyncDriver(registry: $registry);
        $driver->publish('preloaded.event', []);

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_uses_container_based_resolver(): void
    {
        // Simulate a DI container
        $container = [
            FakeEventListener::class => new FakeEventListener(['container']),
        ];

        $resolver = ListenerResolver::from(fn(string $class) => $container[$class] ?? new $class());

        $driver = new SyncDriver(resolver: $resolver);
        $driver->addListener('test.container', FakeEventListener::class);
        $driver->publish('test.container', ['from' => 'container']);

        $this->assertSame(['from' => 'container'], $container[FakeEventListener::class]->getLastEvent());
    }

    #[Test]
    public function it_removes_listener_via_registry(): void
    {
        $registry = new ListenerRegistry();
        $driver = new SyncDriver(registry: $registry);

        $listener = fn() => null;
        $driver->addListener('test.remove', $listener);

        $this->assertTrue($registry->has('test.remove'));

        $driver->removeListener('test.remove', $listener);

        $this->assertFalse($registry->has('test.remove'));
    }
}
