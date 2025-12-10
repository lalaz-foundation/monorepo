<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Common;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Lalaz\Scheduler\Schedule;
use Lalaz\Scheduler\ScheduledEvent;
use Lalaz\Scheduler\ScheduledCommand;
use Lalaz\Scheduler\ScheduledJob;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\CronExpression;
use Lalaz\Scheduler\Mutex\MutexInterface;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Base test case for Scheduler package unit tests.
 *
 * Provides factory methods and assertions for testing
 * scheduled events, cron expressions, and mutex operations.
 *
 * @package lalaz/scheduler
 */
abstract class SchedulerUnitTestCase extends TestCase
{
    /**
     * Default mutex for tests.
     */
    protected NullMutex $mutex;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->mutex = new NullMutex();
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        // Clean up environment variables
        unset($_ENV['APP_ENV']);
        unset($_ENV['SCHEDULE_MODE']);

        parent::tearDown();
    }

    // =========================================================================
    // Factory Methods
    // =========================================================================

    /**
     * Create a Schedule instance for testing.
     */
    protected function createSchedule(
        ?MutexInterface $mutex = null,
        ?string $timezone = null,
        string $mode = 'single'
    ): Schedule {
        return new Schedule(
            $mutex ?? $this->mutex,
            $timezone,
            $mode === 'distributed'
        );
    }

    /**
     * Create a ScheduledClosure for testing.
     */
    protected function createClosureEvent(
        ?callable $callback = null,
        ?MutexInterface $mutex = null,
        bool $distributedMode = false
    ): ScheduledClosure {
        return new ScheduledClosure(
            $callback ?? fn() => 'test result',
            $mutex ?? $this->mutex,
            $distributedMode
        );
    }

    /**
     * Create a ScheduledCommand for testing.
     *
     * @param array<string, mixed> $parameters
     */
    protected function createCommandEvent(
        string $command = 'test:command',
        array $parameters = [],
        ?MutexInterface $mutex = null,
        bool $distributedMode = false
    ): ScheduledCommand {
        return new ScheduledCommand(
            $command,
            $parameters,
            $mutex ?? $this->mutex,
            $distributedMode
        );
    }

    /**
     * Create a ScheduledJob for testing.
     */
    protected function createJobEvent(
        ?object $job = null,
        ?string $queue = null,
        ?MutexInterface $mutex = null,
        bool $distributedMode = false
    ): ScheduledJob {
        return new ScheduledJob(
            $job ?? new \stdClass(),
            $queue,
            $mutex ?? $this->mutex,
            $distributedMode
        );
    }

    /**
     * Create a NullMutex instance.
     */
    protected function createNullMutex(): NullMutex
    {
        return new NullMutex();
    }

    /**
     * Create a DateTimeImmutable for testing.
     */
    protected function createDateTime(
        string $datetime = 'now',
        ?string $timezone = null
    ): DateTimeImmutable {
        $tz = $timezone ? new DateTimeZone($timezone) : null;
        return new DateTimeImmutable($datetime, $tz);
    }

    // =========================================================================
    // Cron Expression Helpers
    // =========================================================================

    /**
     * Check if a cron expression is valid.
     */
    protected function isValidCronExpression(string $expression): bool
    {
        return CronExpression::isValid($expression);
    }

    /**
     * Check if a cron expression is due for a specific date.
     */
    protected function isCronDue(string $expression, ?DateTimeImmutable $date = null): bool
    {
        return CronExpression::isDue($expression, $date);
    }

    /**
     * Get the next run date for a cron expression.
     */
    protected function getNextRunDate(
        string $expression,
        ?DateTimeZone $timezone = null,
        ?DateTimeImmutable $from = null
    ): ?DateTimeImmutable {
        return CronExpression::nextRunDate($expression, $timezone, $from);
    }

    // =========================================================================
    // Schedule Assertions
    // =========================================================================

    /**
     * Assert that a scheduled event has a specific cron expression.
     */
    protected function assertCronExpression(
        string $expected,
        ScheduledEvent $event,
        string $message = ''
    ): void {
        $this->assertSame(
            $expected,
            $event->getExpression(),
            $message ?: "Expected cron expression '{$expected}'"
        );
    }

    /**
     * Assert that a scheduled event is due.
     */
    protected function assertEventIsDue(
        ScheduledEvent $event,
        string $message = ''
    ): void {
        $this->assertTrue(
            $event->isDue(),
            $message ?: 'Event should be due'
        );
    }

    /**
     * Assert that a scheduled event is not due.
     */
    protected function assertEventNotDue(
        ScheduledEvent $event,
        string $message = ''
    ): void {
        $this->assertFalse(
            $event->isDue(),
            $message ?: 'Event should not be due'
        );
    }

    /**
     * Assert that an event has overlap prevention enabled.
     */
    protected function assertPreventsOverlapping(
        ScheduledEvent $event,
        string $message = ''
    ): void {
        $this->assertTrue(
            $event->preventsOverlapping(),
            $message ?: 'Event should prevent overlapping'
        );
    }

    /**
     * Assert that an event runs on one server.
     */
    protected function assertRunsOnOneServer(
        ScheduledEvent $event,
        string $message = ''
    ): void {
        $this->assertTrue(
            $event->runsOnOneServer(),
            $message ?: 'Event should run on one server only'
        );
    }

    /**
     * Assert that an event runs in background.
     */
    protected function assertRunsInBackground(
        ScheduledEvent $event,
        string $message = ''
    ): void {
        $this->assertTrue(
            $event->runsInBackground(),
            $message ?: 'Event should run in background'
        );
    }

    /**
     * Assert that a schedule has a specific number of events.
     */
    protected function assertEventCount(
        int $expected,
        Schedule $schedule,
        string $message = ''
    ): void {
        $this->assertCount(
            $expected,
            $schedule->events(),
            $message ?: "Expected {$expected} scheduled events"
        );
    }

    /**
     * Assert that a schedule has due events.
     */
    protected function assertHasDueEvents(
        Schedule $schedule,
        string $message = ''
    ): void {
        $this->assertNotEmpty(
            $schedule->dueEvents(),
            $message ?: 'Schedule should have due events'
        );
    }

    /**
     * Assert that an event has a specific description.
     */
    protected function assertEventDescription(
        string $expected,
        ScheduledEvent $event,
        string $message = ''
    ): void {
        $this->assertSame(
            $expected,
            $event->getDescription(),
            $message ?: "Expected description '{$expected}'"
        );
    }

    // =========================================================================
    // Environment Helpers
    // =========================================================================

    /**
     * Set the application environment.
     */
    protected function setEnvironment(string $env): void
    {
        $_ENV['APP_ENV'] = $env;
    }

    /**
     * Set the scheduler mode.
     */
    protected function setSchedulerMode(string $mode): void
    {
        $_ENV['SCHEDULE_MODE'] = $mode;
    }

    // =========================================================================
    // Callback Tracking Helpers
    // =========================================================================

    /**
     * Create a tracking callback that records when it was called.
     *
     * @return array{callback: callable, called: bool}
     */
    protected function createTrackingCallback(): array
    {
        $tracker = ['called' => false, 'args' => []];

        $callback = function (...$args) use (&$tracker) {
            $tracker['called'] = true;
            $tracker['args'] = $args;
        };

        return ['callback' => $callback, 'tracker' => &$tracker];
    }

    /**
     * Create an ordered callback tracker.
     *
     * @return array{callbacks: array<string, callable>, order: array}
     */
    protected function createOrderedCallbacks(): array
    {
        $order = [];

        return [
            'order' => &$order,
            'before' => function () use (&$order) { $order[] = 'before'; },
            'task' => function () use (&$order) { $order[] = 'task'; return 'result'; },
            'after' => function () use (&$order) { $order[] = 'after'; },
            'success' => function () use (&$order) { $order[] = 'success'; },
            'failure' => function () use (&$order) { $order[] = 'failure'; },
        ];
    }
}
