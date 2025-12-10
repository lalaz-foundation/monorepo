<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Lalaz\Scheduler\Tests\Common\SchedulerIntegrationTestCase;
use Lalaz\Scheduler\CronExpression;

/**
 * Integration tests for cron expression parsing and evaluation.
 *
 * Tests complex cron scenarios, pattern combinations,
 * and real-world scheduling requirements.
 *
 * @package lalaz/scheduler
 */
class CronExpressionIntegrationTest extends SchedulerIntegrationTestCase
{
    // =========================================================================
    // Standard Cron Patterns
    // =========================================================================

    /**
     * @dataProvider standardCronExpressionsProvider
     */
    public function test_standard_cron_expressions(string $expression, string $description): void
    {
        $this->assertExpressionValid($expression);

        $humanDescription = CronExpression::describe($expression);
        $this->assertNotEmpty($humanDescription);
    }

    public static function standardCronExpressionsProvider(): array
    {
        return [
            'every_minute' => ['* * * * *', 'Every minute'],
            'every_hour' => ['0 * * * *', 'Every hour'],
            'every_day_midnight' => ['0 0 * * *', 'Every day at midnight'],
            'every_day_noon' => ['0 12 * * *', 'Every day at noon'],
            'every_week_sunday' => ['0 0 * * 0', 'Every Sunday at midnight'],
            'every_week_monday' => ['0 0 * * 1', 'Every Monday at midnight'],
            'first_of_month' => ['0 0 1 * *', 'First day of every month'],
            'last_weekday' => ['0 0 * * 1-5', 'Weekdays at midnight'],
            'quarterly' => ['0 0 1 1,4,7,10 *', 'Quarterly'],
            'annual' => ['0 0 1 1 *', 'January 1st at midnight'],
        ];
    }

    // =========================================================================
    // Complex Expression Tests
    // =========================================================================

    public function test_expression_with_step_values(): void
    {
        // Every 5 minutes
        $this->assertExpressionValid('*/5 * * * *');
        $this->assertExpressionDescribes('*/5 * * * *', 'every 5 minutes');

        // Every 2 hours
        $this->assertExpressionValid('0 */2 * * *');

        // Every 10 minutes past the hour
        $this->assertExpressionValid('*/10 * * * *');
    }

    public function test_expression_with_ranges(): void
    {
        // Business hours (9-17)
        $this->assertExpressionValid('0 9-17 * * *');

        // Weekend days (Saturday-Sunday, using 0 and 6)
        $this->assertExpressionValid('0 0 * * 0,6');

        // First quarter months (January-March)
        $this->assertExpressionValid('0 0 1 1-3 *');
    }

    public function test_expression_with_lists(): void
    {
        // Specific hours
        $this->assertExpressionValid('0 8,12,18 * * *');

        // Specific days of month
        $this->assertExpressionValid('0 0 1,15 * *');

        // Specific months
        $this->assertExpressionValid('0 0 1 1,6,12 *');
    }

    public function test_expression_with_combined_features(): void
    {
        // Business hours on weekdays
        $this->assertExpressionValid('0 9-17 * * 1-5');

        // Every 15 minutes during business hours
        $this->assertExpressionValid('*/15 9-17 * * 1-5');

        // First and fifteenth of each month at noon
        $this->assertExpressionValid('0 12 1,15 * *');
    }

    // =========================================================================
    // isDue Tests with Specific Dates
    // =========================================================================

    public function test_is_due_at_specific_times(): void
    {
        // Test every minute - always due
        $this->assertExpressionDueAt(
            '* * * * *',
            new DateTimeImmutable('2024-01-15 14:30:00')
        );

        // Test hourly at minute 0
        $this->assertExpressionDueAt(
            '0 * * * *',
            new DateTimeImmutable('2024-01-15 14:00:00')
        );

        $this->assertExpressionNotDueAt(
            '0 * * * *',
            new DateTimeImmutable('2024-01-15 14:30:00')
        );

        // Test daily at noon
        $this->assertExpressionDueAt(
            '0 12 * * *',
            new DateTimeImmutable('2024-01-15 12:00:00')
        );

        $this->assertExpressionNotDueAt(
            '0 12 * * *',
            new DateTimeImmutable('2024-01-15 14:00:00')
        );
    }

    public function test_is_due_on_specific_days(): void
    {
        // Monday (day 1)
        $monday = new DateTimeImmutable('2024-01-15 00:00:00'); // Monday

        $this->assertExpressionDueAt('0 0 * * 1', $monday);
        $this->assertExpressionNotDueAt('0 0 * * 0', $monday); // Sunday

        // Weekdays
        $this->assertExpressionDueAt('0 0 * * 1-5', $monday);

        // Weekend
        $saturday = new DateTimeImmutable('2024-01-20 00:00:00');
        $this->assertExpressionDueAt('0 0 * * 6', $saturday);
        $this->assertExpressionNotDueAt('0 0 * * 1-5', $saturday);
    }

    public function test_is_due_on_specific_dates(): void
    {
        // First of month
        $firstOfMonth = new DateTimeImmutable('2024-01-01 00:00:00');
        $this->assertExpressionDueAt('0 0 1 * *', $firstOfMonth);
        $this->assertExpressionNotDueAt('0 0 15 * *', $firstOfMonth);

        // Specific month
        $january = new DateTimeImmutable('2024-01-01 00:00:00');
        $this->assertExpressionDueAt('0 0 1 1 *', $january);

        $february = new DateTimeImmutable('2024-02-01 00:00:00');
        $this->assertExpressionNotDueAt('0 0 1 1 *', $february);
    }

    // =========================================================================
    // Next Run Date Tests
    // =========================================================================

    public function test_next_run_date_calculation(): void
    {
        $now = new DateTimeImmutable('2024-01-15 14:30:00');

        // Every minute - should be next minute
        // Signature: nextRunDate(string $expression, ?DateTimeZone $timezone = null, ?DateTimeImmutable $from = null)
        $nextMinute = CronExpression::nextRunDate('* * * * *', null, $now);
        $this->assertNotNull($nextMinute);
        $this->assertEquals(31, (int)$nextMinute->format('i'));

        // Next hour
        $nextHour = CronExpression::nextRunDate('0 * * * *', null, $now);
        $this->assertNotNull($nextHour);
        $this->assertEquals(15, (int)$nextHour->format('H'));
        $this->assertEquals(0, (int)$nextHour->format('i'));
    }

    public function test_next_run_date_for_daily_expression(): void
    {
        $now = new DateTimeImmutable('2024-01-15 14:30:00');

        // Daily at noon - next day since 14:30 > 12:00
        $nextNoon = CronExpression::nextRunDate('0 12 * * *', null, $now);
        $this->assertNotNull($nextNoon);
        $this->assertEquals(16, (int)$nextNoon->format('d'));
        $this->assertEquals(12, (int)$nextNoon->format('H'));

        // Daily at 18:00 - same day
        $nextEvening = CronExpression::nextRunDate('0 18 * * *', null, $now);
        $this->assertNotNull($nextEvening);
        $this->assertEquals(15, (int)$nextEvening->format('d'));
        $this->assertEquals(18, (int)$nextEvening->format('H'));
    }

    public function test_next_run_date_for_weekly_expression(): void
    {
        // Monday 2024-01-15 14:30
        $monday = new DateTimeImmutable('2024-01-15 14:30:00');

        // Next Friday
        $nextFriday = CronExpression::nextRunDate('0 0 * * 5', null, $monday);
        $this->assertNotNull($nextFriday);
        $this->assertEquals(5, (int)$nextFriday->format('w')); // Friday

        // Next Monday (same weekday, so next week)
        $nextMonday = CronExpression::nextRunDate('0 0 * * 1', null, $monday);
        $this->assertNotNull($nextMonday);
        $this->assertEquals(1, (int)$nextMonday->format('w')); // Monday
        $this->assertGreaterThan($monday, $nextMonday);
    }

    // =========================================================================
    // Validation Tests
    // =========================================================================

    /**
     * @dataProvider invalidExpressionsProvider
     */
    public function test_invalid_expressions_are_detected(string $expression): void
    {
        $this->assertExpressionInvalid($expression);
    }

    public static function invalidExpressionsProvider(): array
    {
        // Note: CronExpression::isValid() only checks field count (5 fields)
        // It does NOT validate individual field value ranges
        return [
            'empty' => [''],
            'too_few_fields' => ['* * *'],
            'too_many_fields' => ['* * * * * *'],
            // Fields with invalid values are NOT caught by isValid() - they throw at runtime
        ];
    }

    /**
     * @dataProvider validExpressionsProvider
     */
    public function test_valid_expressions_are_accepted(string $expression): void
    {
        $this->assertExpressionValid($expression);
    }

    public static function validExpressionsProvider(): array
    {
        return [
            'wildcards' => ['* * * * *'],
            'specific_values' => ['30 14 15 6 3'],
            'ranges' => ['0-30 9-17 1-15 1-6 1-5'],
            'lists' => ['0,15,30,45 8,12,18 1,15 1,6,12 0,6'],
            'steps' => ['*/5 */2 */3 */2 *'],
            'combined' => ['0,30 9-17 1-15 * 1-5'],
        ];
    }

    // =========================================================================
    // Edge Cases Tests
    // =========================================================================

    public function test_leap_year_handling(): void
    {
        // Feb 29 exists in leap year
        $leapYear = new DateTimeImmutable('2024-02-29 00:00:00');
        $this->assertExpressionDueAt('0 0 29 2 *', $leapYear);

        // Non-leap year doesn't have Feb 29
        $nonLeapYear = new DateTimeImmutable('2023-02-28 00:00:00');
        $this->assertExpressionNotDueAt('0 0 29 2 *', $nonLeapYear);
    }

    public function test_month_boundaries(): void
    {
        // 31st - only some months
        $jan31 = new DateTimeImmutable('2024-01-31 00:00:00');
        $this->assertExpressionDueAt('0 0 31 * *', $jan31);

        // April has only 30 days
        $apr30 = new DateTimeImmutable('2024-04-30 00:00:00');
        $this->assertExpressionDueAt('0 0 30 4 *', $apr30);
        $this->assertExpressionNotDueAt('0 0 31 4 *', $apr30);
    }

    public function test_year_boundary(): void
    {
        // Dec 31 to Jan 1 transition
        $dec31 = new DateTimeImmutable('2024-12-31 23:59:00');
        $nextYearEvent = CronExpression::nextRunDate('0 0 1 1 *', null, $dec31);

        $this->assertNotNull($nextYearEvent);
        $this->assertEquals(2025, (int)$nextYearEvent->format('Y'));
        $this->assertEquals(1, (int)$nextYearEvent->format('m'));
        $this->assertEquals(1, (int)$nextYearEvent->format('d'));
    }

    public function test_weekend_detection(): void
    {
        // Saturday = 6
        $saturday = new DateTimeImmutable('2024-01-20 00:00:00');
        $this->assertExpressionDueAt('0 0 * * 6', $saturday);
        $this->assertExpressionDueAt('0 0 * * 6,0', $saturday);

        // Sunday = 0
        $sunday = new DateTimeImmutable('2024-01-21 00:00:00');
        $this->assertExpressionDueAt('0 0 * * 0', $sunday);
        $this->assertExpressionDueAt('0 0 * * 6,0', $sunday);
    }

    // =========================================================================
    // Description Tests
    // =========================================================================

    public function test_human_readable_descriptions(): void
    {
        // CronExpression::describe() returns common patterns or the expression itself
        // Only test exact matches from the describe() lookup table
        $this->assertEquals('Every minute', CronExpression::describe('* * * * *'));
        $this->assertEquals('Every 5 minutes', CronExpression::describe('*/5 * * * *'));
        $this->assertEquals('Every hour', CronExpression::describe('0 * * * *'));
        $this->assertEquals('Daily at midnight', CronExpression::describe('0 0 * * *'));
    }

    // =========================================================================
    // Performance Tests
    // =========================================================================

    public function test_expression_parsing_performance(): void
    {
        $expressions = [
            '* * * * *',
            '*/5 * * * *',
            '0 9-17 * * 1-5',
            '0,15,30,45 8,12,18 1,15 1,6,12 0,6',
        ];

        $start = microtime(true);
        $iterations = 1000;

        foreach ($expressions as $expression) {
            for ($i = 0; $i < $iterations; $i++) {
                CronExpression::isValid($expression);
                CronExpression::isDue($expression);
            }
        }

        $elapsed = microtime(true) - $start;

        // Should complete quickly (less than 1 second for 4000 operations)
        $this->assertLessThan(1.0, $elapsed);
    }

    // =========================================================================
    // Real-World Pattern Tests
    // =========================================================================

    public function test_real_world_backup_schedule(): void
    {
        // Daily backup at 2am
        $this->assertExpressionValid('0 2 * * *');

        // Weekly full backup on Sunday at 3am
        $this->assertExpressionValid('0 3 * * 0');

        // Monthly archive on 1st at 4am
        $this->assertExpressionValid('0 4 1 * *');
    }

    public function test_real_world_maintenance_windows(): void
    {
        // Tuesday maintenance window 2-4am
        $this->assertExpressionValid('0 2 * * 2');

        // Quarterly maintenance on first Saturday
        $this->assertExpressionValid('0 2 1-7 1,4,7,10 6');
    }

    public function test_real_world_report_schedules(): void
    {
        // Hourly status report during business hours
        $this->assertExpressionValid('0 9-17 * * 1-5');

        // End of day report at 6pm on weekdays
        $this->assertExpressionValid('0 18 * * 1-5');

        // Weekly summary Friday at 5pm
        $this->assertExpressionValid('0 17 * * 5');

        // Monthly report on last day at midnight
        $this->assertExpressionValid('0 0 28-31 * *');
    }

    // =========================================================================
    // Timezone Tests
    // =========================================================================

    public function test_next_run_date_with_timezone(): void
    {
        $timezone = new DateTimeZone('America/New_York');
        $now = new DateTimeImmutable('2024-01-15 14:30:00', $timezone);

        // Next hour in specific timezone
        $nextHour = CronExpression::nextRunDate('0 * * * *', $timezone, $now);
        $this->assertNotNull($nextHour);
    }

    // =========================================================================
    // Helper Assertions
    // =========================================================================

    private function assertExpressionValid(string $expression): void
    {
        $this->assertTrue(
            CronExpression::isValid($expression),
            "Expression '{$expression}' should be valid"
        );
    }

    private function assertExpressionInvalid(string $expression): void
    {
        $this->assertFalse(
            CronExpression::isValid($expression),
            "Expression '{$expression}' should be invalid"
        );
    }

    private function assertExpressionDueAt(string $expression, DateTimeImmutable $date): void
    {
        $this->assertTrue(
            CronExpression::isDue($expression, $date),
            "Expression '{$expression}' should be due at {$date->format('Y-m-d H:i:s')}"
        );
    }

    private function assertExpressionNotDueAt(string $expression, DateTimeImmutable $date): void
    {
        $this->assertFalse(
            CronExpression::isDue($expression, $date),
            "Expression '{$expression}' should NOT be due at {$date->format('Y-m-d H:i:s')}"
        );
    }

    private function assertExpressionDescribes(string $expression, string $expectedPartial): void
    {
        $description = strtolower(CronExpression::describe($expression));
        $this->assertStringContainsString(
            strtolower($expectedPartial),
            $description,
            "Description of '{$expression}' should contain '{$expectedPartial}'"
        );
    }
}
