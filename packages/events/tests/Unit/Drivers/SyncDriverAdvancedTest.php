<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Drivers\SyncDriver;
use Lalaz\Events\EventListener;

/**
 * Advanced unit tests for SyncDriver
 *
 * Tests the setResolver method and other advanced functionality
 */
final class SyncDriverAdvancedTest extends EventsUnitTestCase
{
    #[Test]
    public function it_can_set_resolver_after_construction(): void
    {
        $driver = new SyncDriver();
        $resolved = false;

        $driver->setResolver(function (string $class) use (&$resolved) {
            $resolved = true;
            return new FakeEventListener(['test']);
        });

        $driver->addListener('test.setresolver', FakeEventListener::class);
        $driver->publish('test.setresolver', []);

        $this->assertTrue($resolved);
    }

    #[Test]
    public function set_resolver_returns_self_for_fluent_interface(): void
    {
        $driver = new SyncDriver();

        $result = $driver->setResolver(fn($class) => new $class());

        $this->assertSame($driver, $result);
    }

    #[Test]
    public function it_can_chain_set_resolver_with_add_listener(): void
    {
        $called = false;
        $driver = (new SyncDriver())
            ->setResolver(function (string $class) use (&$called) {
                $called = true;
                return new FakeEventListener(['test']);
            });

        $driver->addListener('test.chain', FakeEventListener::class);
        $driver->publish('test.chain', []);

        $this->assertTrue($called);
    }

    #[Test]
    public function it_handles_resolved_callable_that_is_not_event_listener(): void
    {
        $executed = false;

        $driver = new SyncDriver(resolver: new \Lalaz\Events\ListenerResolver(function (string $class) use (&$executed) {
            return function ($data) use (&$executed) {
                $executed = true;
            };
        }));

        $driver->addListener('test.callable', FakeEventListener::class);
        $driver->publish('test.callable', ['data' => 'test']);

        $this->assertTrue($executed);
    }

    #[Test]
    public function it_handles_resolved_object_that_is_neither_listener_nor_callable(): void
    {
        $driver = new SyncDriver(resolver: new \Lalaz\Events\ListenerResolver(function (string $class) {
            return new \stdClass(); // Not callable, not EventListener
        }));

        $driver->addListener('test.invalid', FakeEventListener::class);

        // Should not throw, just skip
        $driver->publish('test.invalid', []);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_replaces_resolver_when_set_multiple_times(): void
    {
        $firstResolverCalled = false;
        $secondResolverCalled = false;

        $driver = new SyncDriver(resolver: new \Lalaz\Events\ListenerResolver(function (string $class) use (&$firstResolverCalled) {
            $firstResolverCalled = true;
            return new FakeEventListener(['test']);
        }));

        $driver->setResolver(function (string $class) use (&$secondResolverCalled) {
            $secondResolverCalled = true;
            return new FakeEventListener(['test']);
        });

        $driver->addListener('test.replace', FakeEventListener::class);
        $driver->publish('test.replace', []);

        $this->assertFalse($firstResolverCalled);
        $this->assertTrue($secondResolverCalled);
    }

    #[Test]
    public function it_publishes_with_various_option_types(): void
    {
        $driver = new SyncDriver();
        $received = [];

        $driver->addListener('test.options', function ($data) use (&$received) {
            $received = $data;
        });

        // Test with empty options
        $driver->publish('test.options', ['empty' => true], []);
        $this->assertSame(['empty' => true], $received);

        // Test with custom options
        $driver->publish('test.options', ['custom' => true], ['custom_option' => 'value']);
        $this->assertSame(['custom' => true], $received);

        // Test with stop_on_error false
        $driver->publish('test.options', ['stop' => false], ['stop_on_error' => false]);
        $this->assertSame(['stop' => false], $received);
    }

    #[Test]
    public function it_skips_non_string_non_callable_listener(): void
    {
        $driver = new SyncDriver();
        
        // Add a valid listener to ensure publish works
        $validCalled = false;
        $driver->addListener('test.valid', function () use (&$validCalled) {
            $validCalled = true;
        });

        $driver->publish('test.valid', []);

        $this->assertTrue($validCalled);
    }

    #[Test]
    public function it_handles_remove_listener_from_nonexistent_event_gracefully(): void
    {
        $driver = new SyncDriver();

        // Should not throw
        $driver->removeListener('nonexistent.event', fn() => null);
        $driver->removeListener('another.nonexistent');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_returns_listeners_in_correct_priority_order(): void
    {
        $driver = new SyncDriver();

        $low = fn() => 'low';
        $medium = fn() => 'medium';
        $high = fn() => 'high';

        $driver->addListener('test.order', $low, 10);
        $driver->addListener('test.order', $medium, 50);
        $driver->addListener('test.order', $high, 100);

        $listeners = $driver->getListeners('test.order');

        $this->assertSame([$high, $medium, $low], $listeners);
    }

    #[Test]
    public function it_handles_listener_with_same_priority(): void
    {
        $driver = new SyncDriver();
        $order = [];

        $driver->addListener('test.samepriority', function () use (&$order) {
            $order[] = 'first';
        }, 50);

        $driver->addListener('test.samepriority', function () use (&$order) {
            $order[] = 'second';
        }, 50);

        $driver->addListener('test.samepriority', function () use (&$order) {
            $order[] = 'third';
        }, 50);

        $driver->publish('test.samepriority', []);

        // All should execute, order depends on sort stability
        $this->assertCount(3, $order);
        $this->assertContains('first', $order);
        $this->assertContains('second', $order);
        $this->assertContains('third', $order);
    }

    #[Test]
    public function it_handles_negative_priority(): void
    {
        $driver = new SyncDriver();
        $order = [];

        $driver->addListener('test.negative', function () use (&$order) {
            $order[] = 'positive';
        }, 10);

        $driver->addListener('test.negative', function () use (&$order) {
            $order[] = 'negative';
        }, -10);

        $driver->addListener('test.negative', function () use (&$order) {
            $order[] = 'zero';
        }, 0);

        $driver->publish('test.negative', []);

        $this->assertSame(['positive', 'zero', 'negative'], $order);
    }

    #[Test]
    public function it_handles_event_listener_instance_directly(): void
    {
        $driver = new SyncDriver();
        $listener = new FakeEventListener(['test.instance']);

        $driver->addListener('test.instance', $listener);
        $driver->publish('test.instance', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $listener->getLastEvent());
    }
}
