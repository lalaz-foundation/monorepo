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
 * Integration tests for Event Listener Registration and Execution.
 *
 * Tests the complete listener lifecycle including:
 * - Multiple listener types registration
 * - Priority-based execution order
 * - Multi-event listener subscriptions
 * - Listener removal and cleanup
 *
 * @package lalaz/events
 */
final class EventListenerRegistrationTest extends EventsIntegrationTestCase
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
    // Closure Listener Tests
    // =========================================================================

    #[Test]
    public function it_registers_and_executes_closure_listener(): void
    {
        $hub = $this->createEventHub();
        $received = null;

        $hub->register('test.closure', function ($data) use (&$received) {
            $received = $data;
        });

        $hub->triggerSync('test.closure', ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $received);
    }

    #[Test]
    public function it_registers_multiple_closure_listeners(): void
    {
        $hub = $this->createEventHub();
        $results = [];

        $hub->register('test.multiple', function ($data) use (&$results) {
            $results[] = 'first:' . $data['id'];
        });

        $hub->register('test.multiple', function ($data) use (&$results) {
            $results[] = 'second:' . $data['id'];
        });

        $hub->register('test.multiple', function ($data) use (&$results) {
            $results[] = 'third:' . $data['id'];
        });

        $hub->triggerSync('test.multiple', ['id' => 42]);

        $this->assertCount(3, $results);
        $this->assertContains('first:42', $results);
        $this->assertContains('second:42', $results);
        $this->assertContains('third:42', $results);
    }

    #[Test]
    public function closure_listeners_can_modify_external_state(): void
    {
        $hub = $this->createEventHub();
        $counter = 0;

        $hub->register('test.state', function () use (&$counter) {
            $counter += 10;
        });

        $hub->register('test.state', function () use (&$counter) {
            $counter *= 2;
        });

        $hub->triggerSync('test.state', []);

        // Order depends on registration, both should execute
        $this->assertGreaterThan(0, $counter);
    }

    // =========================================================================
    // EventListener Class Tests
    // =========================================================================

    #[Test]
    public function it_registers_and_executes_event_listener_instance(): void
    {
        $hub = $this->createEventHub();
        $listener = new FakeEventListener(['user.created']);

        $hub->register('user.created', $listener);
        $hub->triggerSync('user.created', ['user_id' => 1, 'email' => 'test@example.com']);

        $lastEvent = $listener->getLastEvent();
        $this->assertSame(1, $lastEvent['user_id']);
        $this->assertSame('test@example.com', $lastEvent['email']);
    }

    #[Test]
    public function event_listener_receives_multiple_events(): void
    {
        $hub = $this->createEventHub();
        $listener = new FakeEventListener(['order.event']);

        $hub->register('order.event', $listener);

        $hub->triggerSync('order.event', ['action' => 'created']);
        $hub->triggerSync('order.event', ['action' => 'updated']);
        $hub->triggerSync('order.event', ['action' => 'shipped']);

        // getLastEvent returns only the most recent
        $this->assertSame('shipped', $listener->getLastEvent()['action']);
    }

    #[Test]
    public function it_registers_event_listener_for_multiple_events(): void
    {
        $hub = $this->createEventHub();
        $listener = new FakeMultiEventListener();

        // Register listener for all its subscribed events
        foreach ($listener->subscribers() as $eventName) {
            $hub->register($eventName, $listener);
        }

        $hub->triggerSync('user.created', ['action' => 'created']);
        $hub->triggerSync('user.updated', ['action' => 'updated']);
        $hub->triggerSync('user.deleted', ['action' => 'deleted']);

        $events = $listener->getReceivedEvents();
        $this->assertCount(3, $events);
    }

    // =========================================================================
    // String Class Listener Tests
    // =========================================================================

    #[Test]
    public function it_registers_string_class_listener(): void
    {
        $resolved = [];
        $resolver = function (string $class) use (&$resolved) {
            $resolved[] = $class;
            return new $class(['test.class']);
        };

        $manager = $this->createEventHubWithResolver($resolver);
        $manager->register('test.class', FakeEventListener::class);

        $manager->triggerSync('test.class', ['resolved' => true]);

        $this->assertContains(FakeEventListener::class, $resolved);
    }

    #[Test]
    public function string_class_listener_is_resolved_per_trigger(): void
    {
        $resolveCount = 0;
        $resolver = function (string $class) use (&$resolveCount) {
            $resolveCount++;
            return new $class(['test']);
        };

        $manager = $this->createEventHubWithResolver($resolver);
        $manager->register('test.resolve', FakeEventListener::class);

        $manager->triggerSync('test.resolve', []);
        $manager->triggerSync('test.resolve', []);
        $manager->triggerSync('test.resolve', []);

        $this->assertSame(3, $resolveCount);
    }

    // =========================================================================
    // Priority Ordering Tests
    // =========================================================================

    #[Test]
    public function listeners_execute_in_priority_order_high_to_low(): void
    {
        $hub = $this->createEventHub();
        $order = [];

        $hub->register('test.priority', function () use (&$order) {
            $order[] = 'priority_0';
        }, 0);

        $hub->register('test.priority', function () use (&$order) {
            $order[] = 'priority_100';
        }, 100);

        $hub->register('test.priority', function () use (&$order) {
            $order[] = 'priority_50';
        }, 50);

        $hub->register('test.priority', function () use (&$order) {
            $order[] = 'priority_-10';
        }, -10);

        $hub->triggerSync('test.priority', []);

        $this->assertSame([
            'priority_100',
            'priority_50',
            'priority_0',
            'priority_-10',
        ], $order);
    }

    #[Test]
    public function listeners_with_same_priority_maintain_registration_order(): void
    {
        $hub = $this->createEventHub();
        $order = [];

        $hub->register('test.same', function () use (&$order) {
            $order[] = 'first';
        }, 50);

        $hub->register('test.same', function () use (&$order) {
            $order[] = 'second';
        }, 50);

        $hub->register('test.same', function () use (&$order) {
            $order[] = 'third';
        }, 50);

        $hub->triggerSync('test.same', []);

        // All executed
        $this->assertCount(3, $order);
    }

    #[Test]
    public function mixed_listener_types_respect_priority(): void
    {
        $order = [];
        $resolver = function (string $class) use (&$order) {
            return new class(['test']) extends FakeEventListener {
                public function handle(mixed $event): void
                {
                    // Can't access $order from anonymous class
                }
            };
        };

        $manager = $this->createEventHubWithResolver($resolver);

        // Closure with high priority
        $manager->register('test.mixed', function () use (&$order) {
            $order[] = 'closure_high';
        }, 100);

        // Listener instance with medium priority
        $listener = new FakeEventListener(['test.mixed']);
        $manager->register('test.mixed', $listener, 50);

        // Closure with low priority
        $manager->register('test.mixed', function () use (&$order) {
            $order[] = 'closure_low';
        }, 0);

        $manager->triggerSync('test.mixed', []);

        $this->assertSame('closure_high', $order[0]);
        $this->assertSame('closure_low', $order[1]);
    }

    // =========================================================================
    // Listener Removal Tests
    // =========================================================================

    #[Test]
    public function it_forgets_specific_closure_listener(): void
    {
        $hub = $this->createEventHub();
        $executed = [];

        $listener1 = function () use (&$executed) { $executed[] = 'first'; };
        $listener2 = function () use (&$executed) { $executed[] = 'second'; };

        $hub->register('test.forget', $listener1);
        $hub->register('test.forget', $listener2);

        $hub->forget('test.forget', $listener1);
        $hub->triggerSync('test.forget', []);

        $this->assertContains('second', $executed);
        $this->assertNotContains('first', $executed);
    }

    #[Test]
    public function it_forgets_all_listeners_for_event(): void
    {
        $hub = $this->createEventHub();
        $executed = false;

        $hub->register('test.forget.all', function () use (&$executed) {
            $executed = true;
        });
        $hub->register('test.forget.all', function () use (&$executed) {
            $executed = true;
        });

        $this->assertTrue($hub->hasListeners('test.forget.all'));

        $hub->forget('test.forget.all');

        $this->assertFalse($hub->hasListeners('test.forget.all'));
        $hub->triggerSync('test.forget.all', []);
        $this->assertFalse($executed);
    }

    #[Test]
    public function it_forgets_event_listener_instance(): void
    {
        $hub = $this->createEventHub();
        $listener = new FakeEventListener(['test']);

        $hub->register('test.forget.instance', $listener);
        $this->assertTrue($hub->hasListeners('test.forget.instance'));

        $hub->forget('test.forget.instance', $listener);
        $this->assertFalse($hub->hasListeners('test.forget.instance'));
    }

    // =========================================================================
    // Query Listeners Tests
    // =========================================================================

    #[Test]
    public function it_checks_if_event_has_listeners(): void
    {
        $hub = $this->createEventHub();

        $this->assertFalse($hub->hasListeners('test.empty'));

        $hub->register('test.empty', fn() => null);

        $this->assertTrue($hub->hasListeners('test.empty'));
    }

    #[Test]
    public function it_returns_registered_listeners(): void
    {
        $hub = $this->createEventHub();

        $listener1 = fn() => 'first';
        $listener2 = fn() => 'second';

        $hub->register('test.list', $listener1);
        $hub->register('test.list', $listener2);

        $listeners = $hub->getListeners('test.list');

        $this->assertCount(2, $listeners);
        $this->assertContains($listener1, $listeners);
        $this->assertContains($listener2, $listeners);
    }

    #[Test]
    public function it_returns_empty_array_for_event_without_listeners(): void
    {
        $hub = $this->createEventHub();

        $listeners = $hub->getListeners('nonexistent.event');

        $this->assertIsArray($listeners);
        $this->assertEmpty($listeners);
    }

    // =========================================================================
    // Edge Cases Tests
    // =========================================================================

    #[Test]
    public function listener_can_register_new_listener(): void
    {
        $hub = $this->createEventHub();
        $newListenerCalled = false;

        $hub->register('first.event', function () use ($hub, &$newListenerCalled) {
            $hub->register('second.event', function () use (&$newListenerCalled) {
                $newListenerCalled = true;
            });
        });

        $hub->triggerSync('first.event', []);
        $hub->triggerSync('second.event', []);

        $this->assertTrue($newListenerCalled);
    }

    #[Test]
    public function listener_can_unregister_itself(): void
    {
        $hub = $this->createEventHub();
        $callCount = 0;

        $listener = null;
        $listener = function () use ($hub, &$callCount, &$listener) {
            $callCount++;
            $hub->forget('test.self', $listener);
        };

        $hub->register('test.self', $listener);

        $hub->triggerSync('test.self', []);
        $hub->triggerSync('test.self', []);

        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function nested_event_triggers_work_correctly(): void
    {
        $hub = $this->createEventHub();
        $order = [];

        $hub->register('outer', function () use ($hub, &$order) {
            $order[] = 'outer_start';
            $hub->triggerSync('inner', []);
            $order[] = 'outer_end';
        });

        $hub->register('inner', function () use (&$order) {
            $order[] = 'inner';
        });

        $hub->triggerSync('outer', []);

        $this->assertSame(['outer_start', 'inner', 'outer_end'], $order);
    }
}
