<?php

declare(strict_types=1);

namespace Lalaz\Scheduler\Concerns;

use Closure;
use DateTimeImmutable;
use DateTimeZone;

/**
 * ManagesFilters - Trait for managing schedule filters.
 *
 * @package lalaz/scheduler
 */
trait ManagesFilters
{
    /**
     * Array of filter callbacks.
     *
     * @var array<Closure>
     */
    protected array $filters = [];

    /**
     * Array of reject callbacks.
     *
     * @var array<Closure>
     */
    protected array $rejects = [];

    /**
     * The environments the event should run in.
     *
     * @var array<string>
     */
    protected array $environments = [];

    /**
     * Register a callback to filter when the event should run.
     *
     * @param Closure|bool $callback
     * @return static
     */
    public function when(Closure|bool $callback): static
    {
        if (is_bool($callback)) {
            $this->filters[] = fn () => $callback;
        } else {
            $this->filters[] = $callback;
        }

        return $this;
    }

    /**
     * Register a callback to reject running the event.
     *
     * @param Closure|bool $callback
     * @return static
     */
    public function skip(Closure|bool $callback): static
    {
        if (is_bool($callback)) {
            $this->rejects[] = fn () => $callback;
        } else {
            $this->rejects[] = $callback;
        }

        return $this;
    }

    /**
     * Only run in specific environments.
     *
     * @param string|string[] $environments
     * @return static
     */
    public function environments(string|array $environments): static
    {
        $this->environments = is_array($environments) ? $environments : [$environments];
        return $this;
    }

    /**
     * Only run in production environment.
     *
     * @return static
     */
    public function production(): static
    {
        return $this->environments('production');
    }

    /**
     * Only run when NOT in production environment.
     *
     * @return static
     */
    public function exceptProduction(): static
    {
        return $this->skip(function () {
            return $this->getCurrentEnvironment() === 'production';
        });
    }

    /**
     * Run only between certain hours.
     *
     * @param string $startTime Start time in HH:MM format
     * @param string $endTime End time in HH:MM format
     * @return static
     */
    public function between(string $startTime, string $endTime): static
    {
        return $this->when(function () use ($startTime, $endTime) {
            return $this->inTimeInterval($startTime, $endTime);
        });
    }

    /**
     * Skip running between certain hours.
     *
     * @param string $startTime Start time in HH:MM format
     * @param string $endTime End time in HH:MM format
     * @return static
     */
    public function unlessBetween(string $startTime, string $endTime): static
    {
        return $this->skip(function () use ($startTime, $endTime) {
            return $this->inTimeInterval($startTime, $endTime);
        });
    }

    /**
     * Check if all filters pass.
     *
     * @return bool
     */
    protected function filtersPass(): bool
    {
        // Check environments first
        if (!empty($this->environments)) {
            $currentEnv = $this->getCurrentEnvironment();
            if (!in_array($currentEnv, $this->environments, true)) {
                return false;
            }
        }

        // Check all filter callbacks
        foreach ($this->filters as $filter) {
            if (!$filter()) {
                return false;
            }
        }

        // Check no reject callbacks match
        foreach ($this->rejects as $reject) {
            if ($reject()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the current time is within a time interval.
     *
     * @param string $startTime Start time in HH:MM format
     * @param string $endTime End time in HH:MM format
     * @return bool
     */
    protected function inTimeInterval(string $startTime, string $endTime): bool
    {
        $timezone = new DateTimeZone($this->timezone ?? date_default_timezone_get());
        $now = new DateTimeImmutable('now', $timezone);

        $start = DateTimeImmutable::createFromFormat('H:i', $startTime, $timezone);
        $end = DateTimeImmutable::createFromFormat('H:i', $endTime, $timezone);

        if ($start === false || $end === false) {
            return false;
        }

        // Handle overnight intervals (e.g., 22:00 to 06:00)
        if ($start > $end) {
            return $now >= $start || $now <= $end;
        }

        return $now >= $start && $now <= $end;
    }

    /**
     * Get the current environment.
     *
     * @return string
     */
    protected function getCurrentEnvironment(): string
    {
        return $_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
    }
}
