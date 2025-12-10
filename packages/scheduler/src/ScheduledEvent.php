<?php

declare(strict_types=1);

namespace Lalaz\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use Lalaz\Scheduler\Concerns\ManagesFilters;
use Lalaz\Scheduler\Concerns\ManagesFrequencies;
use Lalaz\Scheduler\Concerns\ManagesOutput;
use Lalaz\Scheduler\Mutex\MutexInterface;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * ScheduledEvent - Base class for all scheduled tasks.
 *
 * Provides common functionality for scheduling including cron expressions,
 * filters, mutex/overlap handling, and output management.
 *
 * @package lalaz/scheduler
 */
abstract class ScheduledEvent
{
    use ManagesFrequencies;
    use ManagesFilters;
    use ManagesOutput;

    /**
     * The cron expression for the event.
     */
    protected string $expression = '* * * * *';

    /**
     * Human-readable description of the task.
     */
    protected ?string $description = null;

    /**
     * The mutex implementation.
     */
    protected MutexInterface $mutex;

    /**
     * Whether overlap prevention is enabled.
     */
    protected bool $withoutOverlapping = false;

    /**
     * Minutes until the mutex expires (default: 24 hours).
     */
    protected int $expiresAt = 1440;

    /**
     * Whether to run on only one server in distributed mode.
     */
    protected bool $onOneServer = false;

    /**
     * Whether to run in the background.
     */
    protected bool $runInBackground = false;

    /**
     * Whether the scheduler is in distributed mode.
     */
    protected bool $distributedMode = false;

    /**
     * The timezone for this event.
     */
    protected ?DateTimeZone $timezone = null;

    /**
     * Creates a new scheduled event.
     *
     * @param MutexInterface|null $mutex
     * @param bool $distributedMode
     */
    public function __construct(
        ?MutexInterface $mutex = null,
        bool $distributedMode = false
    ) {
        $this->mutex = $mutex ?? new NullMutex();
        $this->distributedMode = $distributedMode;
    }

    /**
     * Execute the scheduled task.
     *
     * @return mixed
     */
    abstract public function run(): mixed;

    /**
     * Get a unique identifier for this event.
     *
     * @return string
     */
    abstract public function mutexName(): string;

    /**
     * Get a summary description of the event.
     *
     * @return string
     */
    abstract public function getSummary(): string;

    /**
     * Check if the event is due to run.
     *
     * @return bool
     */
    public function isDue(): bool
    {
        $now = new DateTimeImmutable('now', $this->timezone);

        return $this->expressionPasses($now) && $this->filtersPass();
    }

    /**
     * Check if the cron expression matches the given time.
     *
     * @param DateTimeImmutable $date
     * @return bool
     */
    protected function expressionPasses(DateTimeImmutable $date): bool
    {
        return CronExpression::isDue($this->expression, $date);
    }

    /**
     * Attempt to run the event with mutex protection if needed.
     *
     * @return mixed
     */
    public function runWithMutex(): mixed
    {
        // In single mode, ignore mutex settings
        if (!$this->distributedMode) {
            return $this->run();
        }

        // Check overlap prevention
        if ($this->withoutOverlapping && !$this->mutex->acquire($this)) {
            return null; // Already running
        }

        // Check one-server constraint
        if ($this->onOneServer && !$this->mutex->acquireOnce($this)) {
            return null; // Another server is handling it
        }

        try {
            return $this->run();
        } finally {
            if ($this->withoutOverlapping || $this->onOneServer) {
                $this->mutex->release($this);
            }
        }
    }

    /**
     * Set a description for the task.
     *
     * @param string $description
     * @return static
     */
    public function description(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the task description.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Prevent overlapping executions.
     *
     * In single mode, this is a no-op.
     * In distributed mode, uses mutex locking.
     *
     * @param int $expiresAt Minutes until the lock expires
     * @return static
     */
    public function withoutOverlapping(int $expiresAt = 1440): static
    {
        $this->withoutOverlapping = true;
        $this->expiresAt = $expiresAt;
        return $this;
    }

    /**
     * Run only on one server in a distributed environment.
     *
     * In single mode, this is a no-op.
     *
     * @return static
     */
    public function onOneServer(): static
    {
        $this->onOneServer = true;
        return $this;
    }

    /**
     * Run the task in the background (non-blocking).
     *
     * @return static
     */
    public function runInBackground(): static
    {
        $this->runInBackground = true;
        return $this;
    }

    /**
     * Check if the task should run in background.
     *
     * @return bool
     */
    public function shouldRunInBackground(): bool
    {
        return $this->runInBackground;
    }

    /**
     * Set the timezone for this event.
     *
     * @param string|DateTimeZone $timezone
     * @return static
     */
    public function timezone(string|DateTimeZone $timezone): static
    {
        $this->timezone = is_string($timezone)
            ? new DateTimeZone($timezone)
            : $timezone;

        return $this;
    }

    /**
     * Get the cron expression.
     *
     * @return string
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get the mutex expiration time in minutes.
     *
     * @return int
     */
    public function getExpiresAt(): int
    {
        return $this->expiresAt;
    }

    /**
     * Check if overlap prevention is enabled.
     *
     * @return bool
     */
    public function preventsOverlapping(): bool
    {
        return $this->withoutOverlapping;
    }

    /**
     * Check if one-server mode is enabled.
     *
     * @return bool
     */
    public function runsOnOneServer(): bool
    {
        return $this->onOneServer;
    }

    /**
     * Check if the task runs in background.
     *
     * @return bool
     */
    public function runsInBackground(): bool
    {
        return $this->runInBackground;
    }

    /**
     * Calculate the next run time.
     *
     * @return DateTimeImmutable|null
     */
    public function nextRunDate(): ?DateTimeImmutable
    {
        return CronExpression::nextRunDate($this->expression, $this->timezone);
    }

    /**
     * Get options summary for display.
     *
     * @return string[]
     */
    public function getOptions(): array
    {
        $options = [];

        if ($this->withoutOverlapping) {
            $options[] = 'no-overlap';
        }

        if ($this->onOneServer) {
            $options[] = 'one-server';
        }

        if ($this->runInBackground) {
            $options[] = 'background';
        }

        return $options;
    }
}
