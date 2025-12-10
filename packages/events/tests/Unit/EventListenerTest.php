<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Tests\Common\FakeEventListener;
use Lalaz\Events\Tests\Common\FakeMultiEventListener;
use Lalaz\Events\EventListener;

/**
 * Unit tests for EventListener
 *
 * Tests the abstract EventListener base class and its
 * implementations for subscribing to and handling events.
 */
final class EventListenerTest extends EventsUnitTestCase
{
    #[Test]
    public function it_returns_array_of_event_names(): void
    {
        $listener = new FakeMultiEventListener();

        $subscribers = $listener->subscribers();

        $this->assertIsArray($subscribers);
        $this->assertContains('user.created', $subscribers);
        $this->assertContains('user.updated', $subscribers);
    }

    #[Test]
    public function it_can_return_single_event(): void
    {
        $listener = new FakeEventListener(['single.event']);

        $subscribers = $listener->subscribers();

        $this->assertCount(1, $subscribers);
        $this->assertSame(['single.event'], $subscribers);
    }

    #[Test]
    public function it_can_return_empty_array(): void
    {
        $listener = new FakeEventListener([]);

        $subscribers = $listener->subscribers();

        $this->assertIsArray($subscribers);
        $this->assertEmpty($subscribers);
    }

    #[Test]
    public function it_receives_event_data(): void
    {
        $listener = new FakeEventListener(['test']);

        $eventData = ['user_id' => 1, 'name' => 'John'];
        $listener->handle($eventData);

        $this->assertSame($eventData, $listener->getLastEvent());
    }

    #[Test]
    public function it_can_be_called_multiple_times(): void
    {
        $listener = new FakeEventListener(['test']);

        $listener->handle(['event' => 1]);
        $listener->handle(['event' => 2]);
        $listener->handle(['event' => 3]);

        $this->assertSame(3, $listener->getHandleCount());
        $this->assertSame(['event' => 3], $listener->getLastEvent());
    }

    #[Test]
    public function it_accepts_various_data_types(): void
    {
        $listener = new FakeEventListener(['test']);

        // Array
        $listener->handle(['key' => 'value']);
        $this->assertSame(['key' => 'value'], $listener->getLastEvent());

        // String
        $listener->handle('string event');
        $this->assertSame('string event', $listener->getLastEvent());

        // Object
        $obj = new \stdClass();
        $obj->property = 'value';
        $listener->handle($obj);
        $this->assertSame($obj, $listener->getLastEvent());

        // Integer
        $listener->handle(42);
        $this->assertSame(42, $listener->getLastEvent());

        // Null
        $listener->handle(null);
        $this->assertNull($listener->getLastEvent());
    }

    #[Test]
    public function it_is_instance_of_abstract_class(): void
    {
        $listener = new FakeEventListener(['test']);

        $this->assertInstanceOf(EventListener::class, $listener);
    }

    #[Test]
    public function it_maintains_independent_instances(): void
    {
        $listener1 = new FakeEventListener(['test']);
        $listener2 = new FakeEventListener(['test']);

        $listener1->handle(['id' => 1]);
        $listener2->handle(['id' => 2]);

        $this->assertSame(['id' => 1], $listener1->getLastEvent());
        $this->assertSame(['id' => 2], $listener2->getLastEvent());
    }
}
