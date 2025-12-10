<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\QueueAvailabilityChecker;

/**
 * Unit tests for QueueAvailabilityChecker
 *
 * Tests the queue availability checking functionality
 */
final class QueueAvailabilityCheckerTest extends EventsUnitTestCase
{
    #[Test]
    public function it_creates_checker_instance(): void
    {
        $checker = new QueueAvailabilityChecker();

        $this->assertInstanceOf(QueueAvailabilityChecker::class, $checker);
    }

    #[Test]
    public function it_returns_boolean_for_availability(): void
    {
        $checker = new QueueAvailabilityChecker();

        $result = $checker->isAvailable();

        $this->assertIsBool($result);
    }

    #[Test]
    public function it_returns_false_when_queue_not_enabled(): void
    {
        // Since we don't have QueueManager enabled in tests,
        // it should return false
        $checker = new QueueAvailabilityChecker();

        $this->assertFalse($checker->isAvailable());
    }
}
