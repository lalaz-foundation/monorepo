<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Unit;

use Lalaz\Scheduler\Tests\Common\SchedulerUnitTestCase;
use Lalaz\Scheduler\CronExpression;

/**
 * Tests for CronExpression class.
 *
 * @package lalaz/scheduler
 */
class CronExpressionTest extends SchedulerUnitTestCase
{
    public function test_every_minute_is_always_due(): void
    {
        $this->assertTrue(CronExpression::isDue('* * * * *'));
    }

    public function test_validates_cron_expressions(): void
    {
        $this->assertTrue(CronExpression::isValid('* * * * *'));
        $this->assertTrue(CronExpression::isValid('0 0 * * *'));
        $this->assertTrue(CronExpression::isValid('*/5 * * * *'));
        $this->assertTrue(CronExpression::isValid('0 0 1 1 *'));

        $this->assertFalse(CronExpression::isValid('invalid'));
        $this->assertFalse(CronExpression::isValid('* * *'));
        $this->assertFalse(CronExpression::isValid('* * * * * *'));
    }

    public function test_describe_common_expressions(): void
    {
        $this->assertSame('Every minute', CronExpression::describe('* * * * *'));
        $this->assertSame('Daily at midnight', CronExpression::describe('0 0 * * *'));
        $this->assertSame('Every 5 minutes', CronExpression::describe('*/5 * * * *'));
        $this->assertSame('Every hour', CronExpression::describe('0 * * * *'));
    }

    public function test_next_run_date(): void
    {
        $next = CronExpression::nextRunDate('* * * * *');

        $this->assertNotNull($next);
        $this->assertGreaterThanOrEqual(time(), $next->getTimestamp());
    }

    public function test_minute_field_matching(): void
    {
        // Specific minute
        $now = new \DateTimeImmutable('2024-01-15 10:30:00');

        $this->assertTrue(CronExpression::isDue('30 * * * *', $now));

        // Different minute
        $now = new \DateTimeImmutable('2024-01-15 10:31:00');
        $this->assertFalse(CronExpression::isDue('30 * * * *', $now));
    }

    public function test_hour_field_matching(): void
    {
        $now = new \DateTimeImmutable('2024-01-15 14:00:00');

        $this->assertTrue(CronExpression::isDue('0 14 * * *', $now));

        // Different hour
        $now = new \DateTimeImmutable('2024-01-15 15:00:00');
        $this->assertFalse(CronExpression::isDue('0 14 * * *', $now));
    }

    public function test_day_of_month_field_matching(): void
    {
        $now = new \DateTimeImmutable('2024-01-15 00:00:00');

        $this->assertTrue(CronExpression::isDue('0 0 15 * *', $now));

        // Different day
        $now = new \DateTimeImmutable('2024-01-16 00:00:00');
        $this->assertFalse(CronExpression::isDue('0 0 15 * *', $now));
    }

    public function test_month_field_matching(): void
    {
        $now = new \DateTimeImmutable('2024-06-01 00:00:00');

        $this->assertTrue(CronExpression::isDue('0 0 1 6 *', $now));

        // Different month
        $now = new \DateTimeImmutable('2024-07-01 00:00:00');
        $this->assertFalse(CronExpression::isDue('0 0 1 6 *', $now));
    }

    public function test_day_of_week_field_matching(): void
    {
        // Monday = 1
        $now = new \DateTimeImmutable('2024-01-15 00:00:00'); // Monday

        $this->assertTrue(CronExpression::isDue('0 0 * * 1', $now));

        // Tuesday
        $now = new \DateTimeImmutable('2024-01-16 00:00:00');
        $this->assertFalse(CronExpression::isDue('0 0 * * 1', $now));
    }

    public function test_step_values(): void
    {
        // Every 5 minutes
        $this->assertTrue(CronExpression::isDue('*/5 * * * *', new \DateTimeImmutable('2024-01-15 10:00:00')));
        $this->assertTrue(CronExpression::isDue('*/5 * * * *', new \DateTimeImmutable('2024-01-15 10:05:00')));
        $this->assertTrue(CronExpression::isDue('*/5 * * * *', new \DateTimeImmutable('2024-01-15 10:10:00')));
        $this->assertFalse(CronExpression::isDue('*/5 * * * *', new \DateTimeImmutable('2024-01-15 10:03:00')));
    }

    public function test_range_values(): void
    {
        // Weekdays only (Mon-Fri = 1-5)
        // Monday
        $this->assertTrue(CronExpression::isDue('0 0 * * 1-5', new \DateTimeImmutable('2024-01-15 00:00:00')));
        // Friday
        $this->assertTrue(CronExpression::isDue('0 0 * * 1-5', new \DateTimeImmutable('2024-01-19 00:00:00')));
        // Saturday
        $this->assertFalse(CronExpression::isDue('0 0 * * 1-5', new \DateTimeImmutable('2024-01-20 00:00:00')));
        // Sunday
        $this->assertFalse(CronExpression::isDue('0 0 * * 1-5', new \DateTimeImmutable('2024-01-21 00:00:00')));
    }

    public function test_list_values(): void
    {
        // Run at 9 AM and 5 PM
        $this->assertTrue(CronExpression::isDue('0 9,17 * * *', new \DateTimeImmutable('2024-01-15 09:00:00')));
        $this->assertTrue(CronExpression::isDue('0 9,17 * * *', new \DateTimeImmutable('2024-01-15 17:00:00')));
        $this->assertFalse(CronExpression::isDue('0 9,17 * * *', new \DateTimeImmutable('2024-01-15 12:00:00')));
    }
}
