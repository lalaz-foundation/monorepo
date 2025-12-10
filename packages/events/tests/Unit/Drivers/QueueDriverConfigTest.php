<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Drivers\QueueDriver;

/**
 * Additional unit tests for QueueDriver
 *
 * Tests configuration and edge cases
 */
final class QueueDriverConfigTest extends EventsUnitTestCase
{
    #[Test]
    public function it_creates_with_all_custom_values(): void
    {
        $driver = new QueueDriver(
            queue: 'custom-queue',
            priority: 5,
            delay: 120
        );

        $this->assertSame('custom-queue', $driver->getQueue());
        $this->assertSame(5, $driver->getPriority());
        $this->assertSame(120, $driver->getDelay());
    }

    #[Test]
    public function it_handles_zero_priority(): void
    {
        $driver = new QueueDriver(priority: 0);

        $this->assertSame(0, $driver->getPriority());
    }

    #[Test]
    public function it_handles_zero_delay(): void
    {
        $driver = new QueueDriver(delay: 0);

        $this->assertSame(0, $driver->getDelay());
    }

    #[Test]
    public function it_handles_negative_delay(): void
    {
        $driver = new QueueDriver(delay: -1);

        $this->assertSame(-1, $driver->getDelay());
    }

    #[Test]
    public function it_handles_empty_queue_name(): void
    {
        $driver = new QueueDriver(queue: '');

        $this->assertSame('', $driver->getQueue());
    }

    #[Test]
    public function it_handles_queue_name_with_special_characters(): void
    {
        $driver = new QueueDriver(queue: 'events:high-priority.v2');

        $this->assertSame('events:high-priority.v2', $driver->getQueue());
    }

    #[Test]
    public function it_is_not_available_without_queue_manager(): void
    {
        $driver = new QueueDriver();

        // In test environment without Queue package configured
        $this->assertFalse($driver->isAvailable());
    }

    #[Test]
    public function it_returns_queue_as_driver_name(): void
    {
        $driver = new QueueDriver();

        $this->assertSame('queue', $driver->getName());
    }

    #[Test]
    public function it_accepts_very_high_priority(): void
    {
        $driver = new QueueDriver(priority: PHP_INT_MAX);

        $this->assertSame(PHP_INT_MAX, $driver->getPriority());
    }

    #[Test]
    public function it_accepts_very_long_delay(): void
    {
        $delay = 60 * 60 * 24 * 365; // 1 year in seconds
        $driver = new QueueDriver(delay: $delay);

        $this->assertSame($delay, $driver->getDelay());
    }
}
