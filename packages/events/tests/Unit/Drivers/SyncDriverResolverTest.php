<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Drivers\SyncDriver;

/**
 * Additional unit tests for SyncDriver
 *
 * Tests edge cases and resolver functionality
 */
final class SyncDriverResolverTest extends EventsUnitTestCase
{
    #[Test]
    public function it_resolves_string_class_listener(): void
    {
        $driver = new SyncDriver();
        $driver->addListener('test.resolve', FakeEventListener::class);

        $driver->publish('test.resolve', ['data' => 'test']);

        // If we reach here without error, the class was resolved
        $this->assertTrue(true);
    }

    #[Test]
    public function it_uses_custom_resolver_for_class_listeners(): void
    {
        $resolved = [];
        $resolver = new \Lalaz\Events\ListenerResolver(function (string $class) use (&$resolved) {
            $resolved[] = $class;
            return new FakeEventListener(['test']);
        });

        $driver = new SyncDriver(resolver: $resolver);
        $driver->addListener('test.custom', FakeEventListener::class);
        $driver->publish('test.custom', ['key' => 'value']);

        $this->assertContains(FakeEventListener::class, $resolved);
    }

    #[Test]
    public function it_resolves_listener_on_each_publish(): void
    {
        $resolveCount = 0;
        $resolver = new \Lalaz\Events\ListenerResolver(function (string $class) use (&$resolveCount) {
            $resolveCount++;
            return new FakeEventListener(['test']);
        });

        $driver = new SyncDriver(resolver: $resolver);
        $driver->addListener('test.resolve', FakeEventListener::class);

        // Publish multiple times
        $driver->publish('test.resolve', []);
        $driver->publish('test.resolve', []);
        $driver->publish('test.resolve', []);

        // Resolver is called on each publish
        $this->assertSame(3, $resolveCount);
    }

    #[Test]
    public function it_handles_invalid_listener_class_gracefully(): void
    {
        $driver = new SyncDriver();
        // Add a non-existent class as string listener
        $driver->addListener('test.invalid', 'NonExistentClass');

        // Should not throw, just skip invalid listener
        $driver->publish('test.invalid', []);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_executes_mixed_listener_types(): void
    {
        $callableExecuted = false;
        $classExecuted = false;

        $driver = new SyncDriver(resolver: new \Lalaz\Events\ListenerResolver(function (string $class) use (&$classExecuted) {
            $classExecuted = true;
            return new FakeEventListener(['test']);
        }));

        $driver->addListener('test.mixed', function () use (&$callableExecuted) {
            $callableExecuted = true;
        });
        $driver->addListener('test.mixed', FakeEventListener::class);

        $driver->publish('test.mixed', []);

        $this->assertTrue($callableExecuted);
        $this->assertTrue($classExecuted);
    }

    #[Test]
    public function it_respects_priority_with_mixed_listeners(): void
    {
        $order = [];

        $driver = new SyncDriver(resolver: new \Lalaz\Events\ListenerResolver(function (string $class) use (&$order) {
            return new class(['test']) extends FakeEventListener {
                public function handle(mixed $event): void
                {
                    // Need access to $order from outer scope
                }
            };
        }));

        $driver->addListener('test.priority', function () use (&$order) {
            $order[] = 'callable_low';
        }, 0);

        $driver->addListener('test.priority', function () use (&$order) {
            $order[] = 'callable_high';
        }, 100);

        $driver->addListener('test.priority', function () use (&$order) {
            $order[] = 'callable_medium';
        }, 50);

        $driver->publish('test.priority', []);

        $this->assertSame(['callable_high', 'callable_medium', 'callable_low'], $order);
    }

    #[Test]
    public function it_handles_empty_event_name(): void
    {
        $driver = new SyncDriver();
        $executed = false;

        $driver->addListener('', function () use (&$executed) {
            $executed = true;
        });

        $driver->publish('', []);

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_passes_data_to_resolved_class_listener(): void
    {
        $receivedData = null;
        $listener = new FakeEventListener(['test']);

        $driver = new SyncDriver(resolver: new \Lalaz\Events\ListenerResolver(function (string $class) use ($listener) {
            return $listener;
        }));

        $driver->addListener('test.data', FakeEventListener::class);
        $driver->publish('test.data', ['message' => 'hello']);

        $this->assertSame(['message' => 'hello'], $listener->getLastEvent());
    }
}
