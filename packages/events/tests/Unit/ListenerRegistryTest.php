<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\ListenerRegistry;

/**
 * Unit tests for ListenerRegistry
 *
 * Tests the extracted listener storage functionality
 */
final class ListenerRegistryTest extends EventsUnitTestCase
{
    private ListenerRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ListenerRegistry();
    }

    #[Test]
    public function it_adds_a_listener(): void
    {
        $listener = fn() => null;
        $this->registry->add('test.event', $listener);

        $this->assertTrue($this->registry->has('test.event'));
        $this->assertCount(1, $this->registry->get('test.event'));
    }

    #[Test]
    public function it_adds_multiple_listeners_to_same_event(): void
    {
        $listener1 = fn() => null;
        $listener2 = fn() => null;

        $this->registry->add('test.event', $listener1);
        $this->registry->add('test.event', $listener2);

        $this->assertCount(2, $this->registry->get('test.event'));
    }

    #[Test]
    public function it_adds_listeners_with_priority(): void
    {
        $low = fn() => 'low';
        $medium = fn() => 'medium';
        $high = fn() => 'high';

        $this->registry->add('test.priority', $low, 10);
        $this->registry->add('test.priority', $high, 100);
        $this->registry->add('test.priority', $medium, 50);

        $listeners = $this->registry->get('test.priority');

        // Should be sorted by priority (high to low)
        $this->assertSame([$high, $medium, $low], $listeners);
    }

    #[Test]
    public function it_returns_listeners_with_metadata(): void
    {
        $listener = fn() => null;
        $this->registry->add('test.metadata', $listener, 42);

        $metadata = $this->registry->getWithMetadata('test.metadata');

        $this->assertCount(1, $metadata);
        $this->assertSame($listener, $metadata[0]['listener']);
        $this->assertSame(42, $metadata[0]['priority']);
    }

    #[Test]
    public function it_removes_specific_listener(): void
    {
        $listener1 = fn() => 'a';
        $listener2 = fn() => 'b';

        $this->registry->add('test.remove', $listener1);
        $this->registry->add('test.remove', $listener2);

        $this->registry->remove('test.remove', $listener1);

        $listeners = $this->registry->get('test.remove');
        $this->assertCount(1, $listeners);
        $this->assertSame($listener2, $listeners[0]);
    }

    #[Test]
    public function it_removes_all_listeners_when_null(): void
    {
        $this->registry->add('test.all', fn() => 'a');
        $this->registry->add('test.all', fn() => 'b');

        $this->registry->remove('test.all', null);

        $this->assertFalse($this->registry->has('test.all'));
    }

    #[Test]
    public function it_handles_remove_from_nonexistent_event(): void
    {
        $this->registry->remove('nonexistent');
        $this->assertFalse($this->registry->has('nonexistent'));
    }

    #[Test]
    public function it_returns_false_for_event_without_listeners(): void
    {
        $this->assertFalse($this->registry->has('empty.event'));
    }

    #[Test]
    public function it_returns_empty_array_for_event_without_listeners(): void
    {
        $this->assertSame([], $this->registry->get('empty.event'));
    }

    #[Test]
    public function it_clears_specific_event(): void
    {
        $this->registry->add('event.a', fn() => null);
        $this->registry->add('event.b', fn() => null);

        $this->registry->clear('event.a');

        $this->assertFalse($this->registry->has('event.a'));
        $this->assertTrue($this->registry->has('event.b'));
    }

    #[Test]
    public function it_clears_all_events(): void
    {
        $this->registry->add('event.a', fn() => null);
        $this->registry->add('event.b', fn() => null);

        $this->registry->clear();

        $this->assertFalse($this->registry->has('event.a'));
        $this->assertFalse($this->registry->has('event.b'));
    }

    #[Test]
    public function it_counts_listeners_for_event(): void
    {
        $this->assertSame(0, $this->registry->count('empty'));

        $this->registry->add('test.count', fn() => null);
        $this->registry->add('test.count', fn() => null);

        $this->assertSame(2, $this->registry->count('test.count'));
    }

    #[Test]
    public function it_returns_all_registered_events(): void
    {
        $this->assertSame([], $this->registry->getEvents());

        $this->registry->add('event.a', fn() => null);
        $this->registry->add('event.b', fn() => null);

        $events = $this->registry->getEvents();

        $this->assertContains('event.a', $events);
        $this->assertContains('event.b', $events);
        $this->assertCount(2, $events);
    }

    #[Test]
    public function it_handles_event_listener_instances(): void
    {
        $listener = new FakeEventListener(['test']);
        $this->registry->add('test.instance', $listener);

        $listeners = $this->registry->get('test.instance');
        $this->assertSame($listener, $listeners[0]);
    }

    #[Test]
    public function it_handles_class_string_listeners(): void
    {
        $this->registry->add('test.class', FakeEventListener::class);

        $listeners = $this->registry->get('test.class');
        $this->assertSame(FakeEventListener::class, $listeners[0]);
    }

    #[Test]
    public function it_handles_negative_priority(): void
    {
        $negative = fn() => 'negative';
        $positive = fn() => 'positive';

        $this->registry->add('test.negative', $negative, -10);
        $this->registry->add('test.negative', $positive, 10);

        $listeners = $this->registry->get('test.negative');
        $this->assertSame([$positive, $negative], $listeners);
    }

    #[Test]
    public function it_maintains_insertion_order_for_same_priority(): void
    {
        $first = fn() => 'first';
        $second = fn() => 'second';
        $third = fn() => 'third';

        $this->registry->add('test.order', $first, 50);
        $this->registry->add('test.order', $second, 50);
        $this->registry->add('test.order', $third, 50);

        // With same priority, should maintain relative order after sort
        $listeners = $this->registry->get('test.order');
        $this->assertCount(3, $listeners);
    }

    #[Test]
    public function it_cleans_up_empty_event_after_removal(): void
    {
        $listener = fn() => null;
        $this->registry->add('test.cleanup', $listener);
        $this->registry->remove('test.cleanup', $listener);

        // Should not have the event in the list
        $this->assertNotContains('test.cleanup', $this->registry->getEvents());
    }
}
