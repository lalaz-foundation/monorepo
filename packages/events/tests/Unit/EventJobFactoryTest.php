<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\EventJobFactory;
use Lalaz\Events\EventJob;

/**
 * Unit tests for EventJobFactory
 *
 * Tests the factory for creating event job dispatches
 */
final class EventJobFactoryTest extends EventsUnitTestCase
{
    #[Test]
    public function it_creates_factory_with_default_job_class(): void
    {
        $factory = new EventJobFactory();

        $this->assertInstanceOf(EventJobFactory::class, $factory);
    }

    #[Test]
    public function it_creates_pending_dispatch(): void
    {
        $factory = new EventJobFactory();

        $dispatch = $factory->create('test-queue', 5);

        $this->assertIsObject($dispatch);
    }

    #[Test]
    public function it_accepts_custom_queue_name(): void
    {
        $factory = new EventJobFactory();

        // Just verify no exception is thrown
        $dispatch = $factory->create('custom-events', 9);

        $this->assertIsObject($dispatch);
    }

    #[Test]
    public function it_accepts_custom_priority(): void
    {
        $factory = new EventJobFactory();

        $dispatch = $factory->create('events', 1);

        $this->assertIsObject($dispatch);
    }

    #[Test]
    public function it_accepts_delay_parameter(): void
    {
        $factory = new EventJobFactory();

        $dispatch = $factory->create('events', 9, 60);

        $this->assertIsObject($dispatch);
    }

    #[Test]
    public function it_creates_dispatch_without_delay(): void
    {
        $factory = new EventJobFactory();

        $dispatch = $factory->create('events', 9, null);

        $this->assertIsObject($dispatch);
    }
}
