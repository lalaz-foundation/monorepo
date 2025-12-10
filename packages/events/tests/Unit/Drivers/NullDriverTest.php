<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Drivers\NullDriver;
use Lalaz\Events\EventHub;

/**
 * Unit tests for NullDriver
 *
 * Tests the NullDriver which is used for testing purposes
 * to record events without actual async processing.
 */
final class NullDriverTest extends EventsUnitTestCase
{
    private NullDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = $this->createNullDriver(recordEvents: true);
    }

    #[Test]
    public function it_returns_null_as_name(): void
    {
        $this->assertSame('null', $this->driver->getName());
    }

    #[Test]
    public function it_is_always_available(): void
    {
        $this->assertTrue($this->driver->isAvailable());
    }

    #[Test]
    public function it_does_not_throw_on_publish(): void
    {
        $this->driver->publish('test.event', ['data' => 'value']);
        $this->assertTrue(true);
    }

    #[Test]
    public function it_records_events_when_record_events_is_true(): void
    {
        $this->driver->publish('user.created', ['user_id' => 1]);
        $this->driver->publish('user.updated', ['user_id' => 1, 'name' => 'John']);

        $this->assertSame(2, $this->driver->count());
    }

    #[Test]
    public function it_does_not_record_events_when_record_events_is_false(): void
    {
        $driver = $this->createNullDriver(recordEvents: false);

        $driver->publish('user.created', ['user_id' => 1]);

        $this->assertSame(0, $driver->count());
    }

    #[Test]
    public function it_stores_event_name_data_and_options(): void
    {
        $this->driver->publish('test.event', ['key' => 'value'], ['priority' => 5]);

        $published = $this->driver->getPublished();

        $this->assertCount(1, $published);
        $this->assertSame('test.event', $published[0]['event']);
        $this->assertSame(['key' => 'value'], $published[0]['data']);
        $this->assertSame(['priority' => 5], $published[0]['options']);
    }

    #[Test]
    public function it_returns_true_for_published_event(): void
    {
        $this->driver->publish('user.created', []);

        $this->assertEventPublished($this->driver, 'user.created');
    }

    #[Test]
    public function it_returns_false_for_non_published_event(): void
    {
        $this->driver->publish('user.created', []);

        $this->assertEventNotPublished($this->driver, 'user.deleted');
    }

    #[Test]
    public function it_returns_false_when_no_events_published(): void
    {
        $this->assertEventNotPublished($this->driver, 'any.event');
    }

    #[Test]
    public function it_returns_all_publications_of_a_specific_event(): void
    {
        $this->driver->publish('user.created', ['id' => 1]);
        $this->driver->publish('user.updated', ['id' => 1]);
        $this->driver->publish('user.created', ['id' => 2]);

        $publications = $this->driver->getPublicationsOf('user.created');

        $this->assertCount(2, $publications);
    }

    #[Test]
    public function it_returns_empty_array_for_non_published_event(): void
    {
        $this->driver->publish('user.created', []);

        $this->assertSame([], $this->driver->getPublicationsOf('user.deleted'));
    }

    #[Test]
    public function it_clears_all_recorded_events(): void
    {
        $this->driver->publish('event1', []);
        $this->driver->publish('event2', []);

        $this->assertSame(2, $this->driver->count());

        $this->driver->clear();

        $this->assertSame(0, $this->driver->count());
        $this->assertSame([], $this->driver->getPublished());
    }

    #[Test]
    public function it_returns_zero_for_empty_driver(): void
    {
        $this->assertSame(0, $this->driver->count());
    }

    #[Test]
    public function it_returns_correct_count_of_published_events(): void
    {
        $this->driver->publish('event1', []);
        $this->driver->publish('event2', []);
        $this->driver->publish('event3', []);

        $this->assertSame(3, $this->driver->count());
    }

    #[Test]
    public function it_can_be_used_to_assert_events_were_triggered(): void
    {
        $hub = new EventHub($this->driver);
        $hub->register('user.created', fn($data) => null);

        $hub->trigger('user.created', ['user_id' => 123]);

        $this->assertEventPublished($this->driver, 'user.created');

        $publications = $this->driver->getPublicationsOf('user.created');
        $this->assertSame(['user_id' => 123], $publications[0]['data']);
    }

    #[Test]
    public function it_can_verify_no_events_were_triggered(): void
    {
        $hub = new EventHub($this->driver);

        $this->assertSame(0, $this->driver->count());
    }

    #[Test]
    public function it_can_verify_event_payload(): void
    {
        $this->driver->publish('order.placed', [
            'order_id' => 456,
            'total' => 99.99,
            'items' => ['item1', 'item2'],
        ]);

        $publications = $this->driver->getPublicationsOf('order.placed');
        $payload = $publications[0]['data'];

        $this->assertSame(456, $payload['order_id']);
        $this->assertSame(99.99, $payload['total']);
        $this->assertCount(2, $payload['items']);
    }
}
