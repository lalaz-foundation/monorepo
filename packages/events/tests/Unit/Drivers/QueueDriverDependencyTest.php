<?php

declare(strict_types=1);

namespace Lalaz\Events\Tests\Unit\Drivers;

use PHPUnit\Framework\Attributes\Test;
use Lalaz\Events\Tests\Common\EventsUnitTestCase;
use Lalaz\Events\Contracts\EventJobFactoryInterface;
use Lalaz\Events\Contracts\QueueAvailabilityCheckerInterface;
use Lalaz\Events\Drivers\QueueDriver;
use Lalaz\Events\EventJobFactory;
use Lalaz\Events\QueueAvailabilityChecker;

/**
 * Tests for QueueDriver dependency injection
 *
 * Tests SOLID compliance - DIP with injected dependencies
 */
final class QueueDriverDependencyTest extends EventsUnitTestCase
{
    #[Test]
    public function it_uses_default_job_factory_when_not_provided(): void
    {
        $driver = new QueueDriver();

        $factory = $driver->getJobFactory();

        $this->assertInstanceOf(EventJobFactoryInterface::class, $factory);
        $this->assertInstanceOf(EventJobFactory::class, $factory);
    }

    #[Test]
    public function it_uses_default_availability_checker_when_not_provided(): void
    {
        $driver = new QueueDriver();

        $checker = $driver->getAvailabilityChecker();

        $this->assertInstanceOf(QueueAvailabilityCheckerInterface::class, $checker);
        $this->assertInstanceOf(QueueAvailabilityChecker::class, $checker);
    }

    #[Test]
    public function it_accepts_custom_job_factory(): void
    {
        $customFactory = new class implements EventJobFactoryInterface {
            public bool $called = false;
            public function create(string $queue, int $priority, ?int $delay = null): object {
                $this->called = true;
                return \Lalaz\Events\EventJob::onQueue($queue)->priority($priority);
            }
        };

        $driver = new QueueDriver(
            queue: 'events',
            priority: 9,
            delay: null,
            dispatcher: null,
            jobFactory: $customFactory
        );

        $this->assertSame($customFactory, $driver->getJobFactory());
    }

    #[Test]
    public function it_accepts_custom_availability_checker(): void
    {
        $customChecker = new class implements QueueAvailabilityCheckerInterface {
            public function isAvailable(): bool {
                return true;
            }
        };

        $driver = new QueueDriver(
            queue: 'events',
            priority: 9,
            delay: null,
            dispatcher: null,
            jobFactory: null,
            availabilityChecker: $customChecker
        );

        $this->assertSame($customChecker, $driver->getAvailabilityChecker());
    }

    #[Test]
    public function it_uses_custom_availability_checker_for_is_available(): void
    {
        $alwaysAvailable = new class implements QueueAvailabilityCheckerInterface {
            public function isAvailable(): bool {
                return true;
            }
        };

        $driver = new QueueDriver(
            queue: 'events',
            priority: 9,
            delay: null,
            dispatcher: null,
            jobFactory: null,
            availabilityChecker: $alwaysAvailable
        );

        $this->assertTrue($driver->isAvailable());
    }

    #[Test]
    public function it_uses_custom_job_factory_when_dispatching(): void
    {
        $factoryCalled = false;
        $dispatchCalled = false;

        $mockDispatch = new class($dispatchCalled) extends \Lalaz\Queue\PendingDispatch {
            private bool $called;
            public function __construct(bool &$called) {
                $this->called = &$called;
                parent::__construct(\Lalaz\Events\EventJob::class);
            }
            public function dispatch(array $payload = []): bool {
                $this->called = true;
                return true;
            }
        };

        $customFactory = new class($factoryCalled, $mockDispatch) implements EventJobFactoryInterface {
            private bool $called;
            private \Lalaz\Queue\PendingDispatch $dispatch;
            public function __construct(bool &$called, \Lalaz\Queue\PendingDispatch $dispatch) {
                $this->called = &$called;
                $this->dispatch = $dispatch;
            }
            public function create(string $queue, int $priority, ?int $delay = null): object {
                $this->called = true;
                return $this->dispatch;
            }
        };

        $driver = new QueueDriver(
            queue: 'events',
            priority: 9,
            delay: null,
            dispatcher: null,
            jobFactory: $customFactory
        );

        $driver->publish('test.event', ['data' => 'value']);

        $this->assertTrue($factoryCalled);
        $this->assertTrue($dispatchCalled);
    }

    #[Test]
    public function it_prefers_dispatcher_over_job_factory(): void
    {
        $dispatcherCalled = false;
        $factoryCalled = false;

        $dispatcher = function () use (&$dispatcherCalled) {
            $dispatcherCalled = true;
        };

        $customFactory = new class($factoryCalled) implements EventJobFactoryInterface {
            private bool $called;
            public function __construct(bool &$called) {
                $this->called = &$called;
            }
            public function create(string $queue, int $priority, ?int $delay = null): object {
                $this->called = true;
                return \Lalaz\Events\EventJob::onQueue($queue);
            }
        };

        $driver = new QueueDriver(
            queue: 'events',
            priority: 9,
            delay: null,
            dispatcher: $dispatcher,
            jobFactory: $customFactory
        );

        $driver->publish('test.event', []);

        $this->assertTrue($dispatcherCalled);
        $this->assertFalse($factoryCalled);
    }

    #[Test]
    public function it_can_inject_all_dependencies(): void
    {
        $customFactory = $this->createMock(EventJobFactoryInterface::class);
        $customChecker = $this->createMock(QueueAvailabilityCheckerInterface::class);

        $driver = new QueueDriver(
            queue: 'custom-queue',
            priority: 5,
            delay: 30,
            dispatcher: fn() => null,
            jobFactory: $customFactory,
            availabilityChecker: $customChecker
        );

        $this->assertSame('custom-queue', $driver->getQueue());
        $this->assertSame(5, $driver->getPriority());
        $this->assertSame(30, $driver->getDelay());
        $this->assertSame($customFactory, $driver->getJobFactory());
        $this->assertSame($customChecker, $driver->getAvailabilityChecker());
    }
}
