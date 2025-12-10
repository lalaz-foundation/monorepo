<?php

declare(strict_types=1);

namespace Lalaz\Scheduler;

use DateTimeImmutable;
use DateTimeZone;

/**
 * CronExpression - Parses and evaluates cron expressions.
 *
 * Supports standard 5-field cron expressions:
 * - minute (0-59)
 * - hour (0-23)
 * - day of month (1-31)
 * - month (1-12)
 * - day of week (0-7, where 0 and 7 are Sunday)
 *
 * Special characters:
 * - * : any value
 * - , : list separator
 * - - : range
 * - / : step
 *
 * @package lalaz/scheduler
 */
final class CronExpression
{
    /**
     * Field positions in the cron expression.
     */
    private const MINUTE = 0;
    private const HOUR = 1;
    private const DAY = 2;
    private const MONTH = 3;
    private const WEEKDAY = 4;

    /**
     * The original cron expression for instance usage.
     *
     * @var string
     */
    private string $expression;

    /**
     * Create a new CronExpression instance for object-oriented usage.
     *
     * This keeps the backward-compatible static API while allowing callers
     * to use the more convenient instance methods (used by the console commands).
     *
     * @param string $expression
     */
    public function __construct(string $expression)
    {
        $this->expression = $expression;
    }

    /**
     * Return the stored expression
     */
    public function expression(): string
    {
        return $this->expression;
    }
    /**
     * Field ranges.
     */
    private const RANGES = [
        self::MINUTE => [0, 59],
        self::HOUR => [0, 23],
        self::DAY => [1, 31],
        self::MONTH => [1, 12],
        self::WEEKDAY => [0, 7],
    ];

    /**
     * Check if a cron expression is due for the given date.
     *
     * @param string $expression The cron expression
     * @param DateTimeImmutable|null $date The date to check (default: now)
     * @return bool
     */
    public static function isDue(string $expression, ?DateTimeImmutable $date = null): bool
    {
        $date ??= new DateTimeImmutable();
        $parts = self::parse($expression);

        // Check each field
        $minute = (int) $date->format('i');
        $hour = (int) $date->format('G');
        $day = (int) $date->format('j');
        $month = (int) $date->format('n');
        $weekday = (int) $date->format('w');

        return self::matches($parts[self::MINUTE], $minute, self::RANGES[self::MINUTE])
            && self::matches($parts[self::HOUR], $hour, self::RANGES[self::HOUR])
            && self::matches($parts[self::DAY], $day, self::RANGES[self::DAY])
            && self::matches($parts[self::MONTH], $month, self::RANGES[self::MONTH])
            && self::matchesWeekday($parts[self::WEEKDAY], $weekday);
    }

    /**
     * Calculate the next run date for a cron expression.
     *
     * @param string $expression
     * @param DateTimeZone|null $timezone
     * @param DateTimeImmutable|null $from
     * @return DateTimeImmutable|null
     */
    public static function nextRunDate(
        string $expression,
        ?DateTimeZone $timezone = null,
        ?DateTimeImmutable $from = null
    ): ?DateTimeImmutable {
        $from ??= new DateTimeImmutable('now', $timezone);

        // Start from the next minute
        $date = $from->modify('+1 minute')->setTime(
            (int) $from->modify('+1 minute')->format('G'),
            (int) $from->modify('+1 minute')->format('i'),
            0
        );

        // Search up to 4 years ahead
        $maxIterations = 60 * 24 * 366 * 4;

        for ($i = 0; $i < $maxIterations; $i++) {
            if (self::isDue($expression, $date)) {
                return $date;
            }
            $date = $date->modify('+1 minute');
        }

        return null;
    }

    /**
     * Parse a cron expression into parts.
     *
     * @param string $expression
     * @return string[]
     */
    private static function parse(string $expression): array
    {
        $parts = preg_split('/\s+/', trim($expression));

        if (count($parts) !== 5) {
            throw new \InvalidArgumentException(
                'Invalid cron expression: expected 5 fields, got ' . count($parts)
            );
        }

        return $parts;
    }

    /**
     * Check if a field matches a value.
     *
     * @param string $field The cron field
     * @param int $value The value to check
     * @param int[] $range [min, max] for the field
     * @return bool
     */
    private static function matches(string $field, int $value, array $range): bool
    {
        // Any value
        if ($field === '*') {
            return true;
        }

        // List of values
        if (str_contains($field, ',')) {
            $values = explode(',', $field);
            foreach ($values as $v) {
                if (self::matches(trim($v), $value, $range)) {
                    return true;
                }
            }
            return false;
        }

        // Range with optional step
        if (str_contains($field, '-') || str_contains($field, '/')) {
            return self::matchesRange($field, $value, $range);
        }

        // Exact value
        return (int) $field === $value;
    }

    /**
     * Check if a range/step field matches a value.
     *
     * @param string $field
     * @param int $value
     * @param int[] $range
     * @return bool
     */
    private static function matchesRange(string $field, int $value, array $range): bool
    {
        $step = 1;

        // Extract step if present
        if (str_contains($field, '/')) {
            [$field, $step] = explode('/', $field, 2);
            $step = (int) $step;
        }

        // Determine range bounds
        if ($field === '*') {
            $min = $range[0];
            $max = $range[1];
        } elseif (str_contains($field, '-')) {
            [$min, $max] = array_map('intval', explode('-', $field, 2));
        } else {
            $min = (int) $field;
            $max = $range[1];
        }

        // Check if value is in range and matches step
        if ($value < $min || $value > $max) {
            return false;
        }

        return ($value - $min) % $step === 0;
    }

    /**
     * Check if weekday matches (handles 0 and 7 both being Sunday).
     *
     * @param string $field
     * @param int $weekday
     * @return bool
     */
    private static function matchesWeekday(string $field, int $weekday): bool
    {
        // Normalize Sunday (7 -> 0)
        if ($weekday === 7) {
            $weekday = 0;
        }

        // Also check for 7 in the expression
        if (self::matches($field, $weekday, self::RANGES[self::WEEKDAY])) {
            return true;
        }

        // If weekday is 0 (Sunday), also check for 7
        if ($weekday === 0 && str_contains($field, '7')) {
            return self::matches(str_replace('7', '0', $field), 0, [0, 6]);
        }

        return false;
    }

    /**
     * Validate a cron expression.
     *
     * @param string $expression
     * @return bool
     */
    public static function isValid(string $expression): bool
    {
        try {
            self::parse($expression);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Get a human-readable description of a cron expression.
     *
     * @param string $expression
     * @return string
     */
    public static function describe(string $expression): string
    {
        $common = [
            '* * * * *' => 'Every minute',
            '*/5 * * * *' => 'Every 5 minutes',
            '*/15 * * * *' => 'Every 15 minutes',
            '*/30 * * * *' => 'Every 30 minutes',
            '0 * * * *' => 'Every hour',
            '0 0 * * *' => 'Daily at midnight',
            '0 0 * * 0' => 'Weekly on Sunday',
            '0 0 1 * *' => 'Monthly on the 1st',
            '0 0 1 1 *' => 'Yearly on January 1st',
        ];

        return $common[$expression] ?? $expression;
    }

    /**
     * Instance - Check if this expression is due for the given date.
     */
    public function isDueInstance(?DateTimeImmutable $date = null): bool
    {
        return self::isDue($this->expression, $date);
    }

    /**
     * Instance - Calculate the next run date for this expression.
     */
    public function nextRunDateInstance(?DateTimeZone $timezone = null, ?DateTimeImmutable $from = null): ?DateTimeImmutable
    {
        return self::nextRunDate($this->expression, $timezone, $from);
    }

    /**
     * Instance - Get a human-friendly description for this expression.
     */
    public function describeInstance(): string
    {
        return self::describe($this->expression);
    }

    /**
     * Magic bridge to allow instance-style calls to the existing static API.
     *
     * This lets callers use $cron->isDue(), $cron->nextRunDate(), $cron->describe()
     * while preserving the static methods used heavily in tests.
     *
    * @param string              $name
    * @param array<int, mixed>   $arguments
     * @return mixed
     */
    public function __call(string $name, array $arguments)
    {
        switch ($name) {
            case 'isDue':
                $date = $arguments[0] ?? null;
                return self::isDue($this->expression, $date);

            case 'nextRunDate':
                $timezone = $arguments[0] ?? null;
                $from = $arguments[1] ?? null;
                return self::nextRunDate($this->expression, $timezone, $from);

            case 'describe':
                return self::describe($this->expression);

            default:
                throw new \BadMethodCallException(sprintf('Method %s does not exist on CronExpression', $name));
        }
    }
}
