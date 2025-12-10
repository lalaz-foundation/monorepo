<?php

declare(strict_types=1);

namespace Lalaz\Scheduler\Concerns;

/**
 * ManagesFrequencies - Trait for scheduling frequency methods.
 *
 * @package lalaz/scheduler
 */
trait ManagesFrequencies
{
    /**
     * The cron expression for the event.
     */
    protected string $expression = '* * * * *';

    /**
     * Set a custom cron expression.
     *
     * @param string $expression
     * @return static
     */
    public function cron(string $expression): static
    {
        $this->expression = $expression;
        return $this;
    }

    /**
     * Run every minute.
     *
     * @return static
     */
    public function everyMinute(): static
    {
        $this->expression = '* * * * *';
        return $this;
    }

    /**
     * Run every two minutes.
     *
     * @return static
     */
    public function everyTwoMinutes(): static
    {
        $this->expression = '*/2 * * * *';
        return $this;
    }

    /**
     * Run every three minutes.
     *
     * @return static
     */
    public function everyThreeMinutes(): static
    {
        $this->expression = '*/3 * * * *';
        return $this;
    }

    /**
     * Run every four minutes.
     *
     * @return static
     */
    public function everyFourMinutes(): static
    {
        $this->expression = '*/4 * * * *';
        return $this;
    }

    /**
     * Run every five minutes.
     *
     * @return static
     */
    public function everyFiveMinutes(): static
    {
        $this->expression = '*/5 * * * *';
        return $this;
    }

    /**
     * Run every ten minutes.
     *
     * @return static
     */
    public function everyTenMinutes(): static
    {
        $this->expression = '*/10 * * * *';
        return $this;
    }

    /**
     * Run every fifteen minutes.
     *
     * @return static
     */
    public function everyFifteenMinutes(): static
    {
        $this->expression = '*/15 * * * *';
        return $this;
    }

    /**
     * Run every thirty minutes.
     *
     * @return static
     */
    public function everyThirtyMinutes(): static
    {
        $this->expression = '*/30 * * * *';
        return $this;
    }

    /**
     * Run hourly.
     *
     * @return static
     */
    public function hourly(): static
    {
        $this->expression = '0 * * * *';
        return $this;
    }

    /**
     * Run hourly at a specific minute.
     *
     * @param int|int[] $offset Minute(s) of the hour
     * @return static
     */
    public function hourlyAt(int|array $offset): static
    {
        $offset = is_array($offset) ? implode(',', $offset) : $offset;
        $this->expression = "{$offset} * * * *";
        return $this;
    }

    /**
     * Run every two hours.
     *
     * @return static
     */
    public function everyTwoHours(): static
    {
        $this->expression = '0 */2 * * *';
        return $this;
    }

    /**
     * Run every three hours.
     *
     * @return static
     */
    public function everyThreeHours(): static
    {
        $this->expression = '0 */3 * * *';
        return $this;
    }

    /**
     * Run every four hours.
     *
     * @return static
     */
    public function everyFourHours(): static
    {
        $this->expression = '0 */4 * * *';
        return $this;
    }

    /**
     * Run every six hours.
     *
     * @return static
     */
    public function everySixHours(): static
    {
        $this->expression = '0 */6 * * *';
        return $this;
    }

    /**
     * Run daily at midnight.
     *
     * @return static
     */
    public function daily(): static
    {
        $this->expression = '0 0 * * *';
        return $this;
    }

    /**
     * Run daily at a specific time.
     *
     * @param string $time Time in HH:MM format
     * @return static
     */
    public function dailyAt(string $time): static
    {
        [$hour, $minute] = $this->parseTime($time);
        $this->expression = "{$minute} {$hour} * * *";
        return $this;
    }

    /**
     * Run twice daily.
     *
     * @param int $firstHour First hour
     * @param int $secondHour Second hour
     * @return static
     */
    public function twiceDaily(int $firstHour = 1, int $secondHour = 13): static
    {
        $this->expression = "0 {$firstHour},{$secondHour} * * *";
        return $this;
    }

    /**
     * Run weekly on Sunday at midnight.
     *
     * @return static
     */
    public function weekly(): static
    {
        $this->expression = '0 0 * * 0';
        return $this;
    }

    /**
     * Run weekly on a specific day and time.
     *
     * @param int $dayOfWeek Day of week (0=Sunday, 6=Saturday)
     * @param string $time Time in HH:MM format
     * @return static
     */
    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): static
    {
        [$hour, $minute] = $this->parseTime($time);
        $this->expression = "{$minute} {$hour} * * {$dayOfWeek}";
        return $this;
    }

    /**
     * Run monthly on the first day at midnight.
     *
     * @return static
     */
    public function monthly(): static
    {
        $this->expression = '0 0 1 * *';
        return $this;
    }

    /**
     * Run monthly on a specific day and time.
     *
     * @param int $dayOfMonth Day of month (1-31)
     * @param string $time Time in HH:MM format
     * @return static
     */
    public function monthlyOn(int $dayOfMonth, string $time = '00:00'): static
    {
        [$hour, $minute] = $this->parseTime($time);
        $this->expression = "{$minute} {$hour} {$dayOfMonth} * *";
        return $this;
    }

    /**
     * Run twice monthly on specific days.
     *
     * @param int $firstDay First day
     * @param int $secondDay Second day
     * @param string $time Time in HH:MM format
     * @return static
     */
    public function twiceMonthly(int $firstDay = 1, int $secondDay = 16, string $time = '00:00'): static
    {
        [$hour, $minute] = $this->parseTime($time);
        $this->expression = "{$minute} {$hour} {$firstDay},{$secondDay} * *";
        return $this;
    }

    /**
     * Run quarterly on the first day at midnight.
     *
     * @return static
     */
    public function quarterly(): static
    {
        $this->expression = '0 0 1 1,4,7,10 *';
        return $this;
    }

    /**
     * Run yearly on January 1st at midnight.
     *
     * @return static
     */
    public function yearly(): static
    {
        $this->expression = '0 0 1 1 *';
        return $this;
    }

    /**
     * Run yearly on a specific date and time.
     *
     * @param int $month Month (1-12)
     * @param int $day Day of month (1-31)
     * @param string $time Time in HH:MM format
     * @return static
     */
    public function yearlyOn(int $month, int $day = 1, string $time = '00:00'): static
    {
        [$hour, $minute] = $this->parseTime($time);
        $this->expression = "{$minute} {$hour} {$day} {$month} *";
        return $this;
    }

    /**
     * Run only on weekdays (Monday-Friday).
     *
     * @return static
     */
    public function weekdays(): static
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '1-5';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Run only on weekends (Saturday-Sunday).
     *
     * @return static
     */
    public function weekends(): static
    {
        $parts = explode(' ', $this->expression);
        $parts[4] = '0,6';
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Run only on Sundays.
     *
     * @return static
     */
    public function sundays(): static
    {
        return $this->days(0);
    }

    /**
     * Run only on Mondays.
     *
     * @return static
     */
    public function mondays(): static
    {
        return $this->days(1);
    }

    /**
     * Run only on Tuesdays.
     *
     * @return static
     */
    public function tuesdays(): static
    {
        return $this->days(2);
    }

    /**
     * Run only on Wednesdays.
     *
     * @return static
     */
    public function wednesdays(): static
    {
        return $this->days(3);
    }

    /**
     * Run only on Thursdays.
     *
     * @return static
     */
    public function thursdays(): static
    {
        return $this->days(4);
    }

    /**
     * Run only on Fridays.
     *
     * @return static
     */
    public function fridays(): static
    {
        return $this->days(5);
    }

    /**
     * Run only on Saturdays.
     *
     * @return static
     */
    public function saturdays(): static
    {
        return $this->days(6);
    }

    /**
     * Run only on specific days of the week.
     *
     * @param int|int[] $days
     * @return static
     */
    public function days(int|array $days): static
    {
        $days = is_array($days) ? implode(',', $days) : $days;
        $parts = explode(' ', $this->expression);
        $parts[4] = (string) $days;
        $this->expression = implode(' ', $parts);
        return $this;
    }

    /**
     * Parse a time string into hour and minute.
     *
     * @param string $time Time in HH:MM format
     * @return int[] [hour, minute]
     */
    private function parseTime(string $time): array
    {
        $parts = explode(':', $time);
        return [
            (int) ($parts[0] ?? 0),
            (int) ($parts[1] ?? 0),
        ];
    }
}
