<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Drivers\QueueDriver;

/**
 * Unit tests for QueueDriver
 *
 * Tests the QueueDriver which handles async event processing
 * through the queue system.
 */
final class QueueDriverTest extends EventsUnitTestCase
{
    private QueueDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new QueueDriver(
            queue: 'events',
            priority: 9,
            delay: null
        );
    }

    #[Test]
    public function it_returns_queue_as_name(): void
    {
        $this->assertSame('queue', $this->driver->getName());
    }

    #[Test]
    public function it_accepts_custom_queue_name(): void
    {
        $driver = new QueueDriver(queue: 'custom-events');
        $this->assertSame('custom-events', $driver->getQueue());
    }

    #[Test]
    public function it_accepts_custom_priority(): void
    {
        $driver = new QueueDriver(priority: 5);
        $this->assertSame(5, $driver->getPriority());
    }

    #[Test]
    public function it_accepts_custom_delay(): void
    {
        $driver = new QueueDriver(delay: 60);
        $this->assertSame(60, $driver->getDelay());
    }

    #[Test]
    public function it_has_default_values(): void
    {
        $driver = new QueueDriver();

        $this->assertSame('events', $driver->getQueue());
        $this->assertSame(9, $driver->getPriority());
        $this->assertNull($driver->getDelay());
    }

    #[Test]
    public function it_returns_the_configured_queue_name(): void
    {
        $this->assertSame('events', $this->driver->getQueue());
    }

    #[Test]
    public function it_returns_the_configured_priority(): void
    {
        $this->assertSame(9, $this->driver->getPriority());
    }

    #[Test]
    public function it_returns_null_when_no_delay_configured(): void
    {
        $this->assertNull($this->driver->getDelay());
    }

    #[Test]
    public function it_returns_configured_delay(): void
    {
        $driver = new QueueDriver(delay: 120);
        $this->assertSame(120, $driver->getDelay());
    }

    #[Test]
    public function it_returns_false_when_queue_manager_is_not_enabled(): void
    {
        $this->assertFalse($this->driver->isAvailable());
    }
}
