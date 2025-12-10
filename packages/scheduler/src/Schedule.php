<?php

declare(strict_types=1);

namespace Lalaz\Scheduler;

use DateTimeZone;
use Lalaz\Scheduler\Mutex\MutexInterface;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Schedule - Main entry point for defining scheduled tasks.
 *
 * Provides a fluent API for defining when commands, jobs, and closures
 * should be executed automatically.
 *
 * @example
 * ```php
 * $schedule = new Schedule();
 *
 * $schedule->command('cache:prune')
 *     ->hourly()
 *     ->description('Remove expired cache');
 *
 * $schedule->job(new ProcessReports)
 *     ->dailyAt('03:00')
 *     ->withoutOverlapping();
 *
 * $schedule->call(fn() => cleanup())
 *     ->everyFifteenMinutes();
 * ```
 *
 * @package lalaz/scheduler
 */
class Schedule
{
    /**
     * All registered scheduled events.
     *
     * @var ScheduledEvent[]
     */
    private array $events = [];

    /**
     * The mutex implementation for overlap prevention.
     */
    private MutexInterface $mutex;

    /**
     * The timezone for evaluating schedule times.
     */
    private ?DateTimeZone $timezone = null;

    /**
     * Whether the scheduler is in distributed mode.
     */
    private bool $distributedMode = false;

    /**
     * Creates a new Schedule instance.
     *
     * @param MutexInterface|null $mutex Mutex for distributed locking
     * @param string|null $timezone Default timezone for tasks
     * @param bool $distributedMode Whether running in distributed mode
     */
    public function __construct(
        ?MutexInterface $mutex = null,
        ?string $timezone = null,
        bool $distributedMode = false
    ) {
        $this->mutex = $mutex ?? new NullMutex();
        $this->distributedMode = $distributedMode;

        if ($timezone !== null) {
            $this->timezone = new DateTimeZone($timezone);
        }
    }

    /**
     * Schedule a console command.
     *
     * @param string $command The command name (without 'php lalaz')
     * @param array<string, mixed> $parameters Command parameters
     * @return ScheduledCommand
     */
    public function command(string $command, array $parameters = []): ScheduledCommand
    {
        $event = new ScheduledCommand(
            $command,
            $parameters,
            $this->mutex,
            $this->distributedMode
        );

        if ($this->timezone !== null) {
            $event->timezone($this->timezone);
        }

        $this->events[] = $event;

        return $event;
    }

    /**
     * Schedule a queued job.
     *
     * @param object $job The job instance
     * @param string|null $queue The queue name (null = default)
     * @return ScheduledJob
     */
    public function job(object $job, ?string $queue = null): ScheduledJob
    {
        $event = new ScheduledJob(
            $job,
            $queue,
            $this->mutex,
            $this->distributedMode
        );

        if ($this->timezone !== null) {
            $event->timezone($this->timezone);
        }

        $this->events[] = $event;

        return $event;
    }

    /**
     * Schedule a closure/callback.
     *
     * @param callable $callback The callback to execute
     * @return ScheduledClosure
     */
    public function call(callable $callback): ScheduledClosure
    {
        $event = new ScheduledClosure(
            $callback,
            $this->mutex,
            $this->distributedMode
        );

        if ($this->timezone !== null) {
            $event->timezone($this->timezone);
        }

        $this->events[] = $event;

        return $event;
    }

    /**
     * Get all scheduled events.
     *
     * @return ScheduledEvent[]
     */
    public function events(): array
    {
        return $this->events;
    }

    /**
     * Get all events that are due to run now.
     *
     * @return ScheduledEvent[]
     */
    public function dueEvents(): array
    {
        return array_filter(
            $this->events,
            fn (ScheduledEvent $event) => $event->isDue()
        );
    }

    /**
     * Check if the scheduler has any events.
     *
     * @return bool
     */
    public function hasEvents(): bool
    {
        return count($this->events) > 0;
    }

    /**
     * Get the number of scheduled events.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->events);
    }

    /**
     * Check if scheduler is in distributed mode.
     *
     * @return bool
     */
    public function isDistributed(): bool
    {
        return $this->distributedMode;
    }

    /**
     * Set the default timezone for all events.
     *
     * @param string|DateTimeZone $timezone
     * @return self
     */
    public function setTimezone(string|DateTimeZone $timezone): self
    {
        $this->timezone = is_string($timezone)
            ? new DateTimeZone($timezone)
            : $timezone;

        return $this;
    }

    /**
     * Set the default timezone for all events.
     *
     * Alias for setTimezone().
     *
     * @param string|DateTimeZone $timezone
     * @return self
     */
    public function timezone(string|DateTimeZone $timezone): self
    {
        return $this->setTimezone($timezone);
    }

    /**
     * Get the default timezone.
     *
     * @return DateTimeZone|null
     */
    public function getTimezone(): ?DateTimeZone
    {
        return $this->timezone;
    }
}
