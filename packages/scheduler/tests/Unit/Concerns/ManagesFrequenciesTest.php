<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Unit\Concerns;

use Lalaz\Scheduler\Tests\Common\SchedulerUnitTestCase;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Tests for ManagesFrequencies trait.
 *
 * @package lalaz/scheduler
 */
class ManagesFrequenciesTest extends SchedulerUnitTestCase
{
    private function createEvent(): ScheduledClosure
    {
        return $this->createClosureEvent();
    }

    public function test_every_minute(): void
    {
        $event = $this->createEvent()->everyMinute();
        $this->assertSame('* * * * *', $event->getExpression());
    }

    public function test_every_two_minutes(): void
    {
        $event = $this->createEvent()->everyTwoMinutes();
        $this->assertSame('*/2 * * * *', $event->getExpression());
    }

    public function test_every_three_minutes(): void
    {
        $event = $this->createEvent()->everyThreeMinutes();
        $this->assertSame('*/3 * * * *', $event->getExpression());
    }

    public function test_every_four_minutes(): void
    {
        $event = $this->createEvent()->everyFourMinutes();
        $this->assertSame('*/4 * * * *', $event->getExpression());
    }

    public function test_every_five_minutes(): void
    {
        $event = $this->createEvent()->everyFiveMinutes();
        $this->assertSame('*/5 * * * *', $event->getExpression());
    }

    public function test_every_ten_minutes(): void
    {
        $event = $this->createEvent()->everyTenMinutes();
        $this->assertSame('*/10 * * * *', $event->getExpression());
    }

    public function test_every_fifteen_minutes(): void
    {
        $event = $this->createEvent()->everyFifteenMinutes();
        $this->assertSame('*/15 * * * *', $event->getExpression());
    }

    public function test_every_thirty_minutes(): void
    {
        $event = $this->createEvent()->everyThirtyMinutes();
        $this->assertSame('*/30 * * * *', $event->getExpression());
    }

    public function test_hourly(): void
    {
        $event = $this->createEvent()->hourly();
        $this->assertSame('0 * * * *', $event->getExpression());
    }

    public function test_hourly_at_single_minute(): void
    {
        $event = $this->createEvent()->hourlyAt(15);
        $this->assertSame('15 * * * *', $event->getExpression());
    }

    public function test_hourly_at_multiple_minutes(): void
    {
        $event = $this->createEvent()->hourlyAt([0, 15, 30, 45]);
        $this->assertSame('0,15,30,45 * * * *', $event->getExpression());
    }

    public function test_every_two_hours(): void
    {
        $event = $this->createEvent()->everyTwoHours();
        $this->assertSame('0 */2 * * *', $event->getExpression());
    }

    public function test_every_three_hours(): void
    {
        $event = $this->createEvent()->everyThreeHours();
        $this->assertSame('0 */3 * * *', $event->getExpression());
    }

    public function test_every_four_hours(): void
    {
        $event = $this->createEvent()->everyFourHours();
        $this->assertSame('0 */4 * * *', $event->getExpression());
    }

    public function test_every_six_hours(): void
    {
        $event = $this->createEvent()->everySixHours();
        $this->assertSame('0 */6 * * *', $event->getExpression());
    }

    public function test_daily(): void
    {
        $event = $this->createEvent()->daily();
        $this->assertSame('0 0 * * *', $event->getExpression());
    }

    public function test_daily_at(): void
    {
        $event = $this->createEvent()->dailyAt('13:30');
        $this->assertSame('30 13 * * *', $event->getExpression());
    }

    public function test_twice_daily(): void
    {
        $event = $this->createEvent()->twiceDaily(1, 13);
        $this->assertSame('0 1,13 * * *', $event->getExpression());
    }

    public function test_weekly(): void
    {
        $event = $this->createEvent()->weekly();
        $this->assertSame('0 0 * * 0', $event->getExpression());
    }

    public function test_weekly_on(): void
    {
        $event = $this->createEvent()->weeklyOn(1, '08:00');
        $this->assertSame('0 8 * * 1', $event->getExpression());
    }

    public function test_monthly(): void
    {
        $event = $this->createEvent()->monthly();
        $this->assertSame('0 0 1 * *', $event->getExpression());
    }

    public function test_monthly_on(): void
    {
        $event = $this->createEvent()->monthlyOn(15, '09:00');
        $this->assertSame('0 9 15 * *', $event->getExpression());
    }

    public function test_twice_monthly(): void
    {
        $event = $this->createEvent()->twiceMonthly(1, 15, '00:00');
        $this->assertSame('0 0 1,15 * *', $event->getExpression());
    }

    public function test_quarterly(): void
    {
        $event = $this->createEvent()->quarterly();
        $this->assertSame('0 0 1 1,4,7,10 *', $event->getExpression());
    }

    public function test_yearly(): void
    {
        $event = $this->createEvent()->yearly();
        $this->assertSame('0 0 1 1 *', $event->getExpression());
    }

    public function test_yearly_on(): void
    {
        $event = $this->createEvent()->yearlyOn(6, 15, '10:00');
        $this->assertSame('0 10 15 6 *', $event->getExpression());
    }

    public function test_weekdays(): void
    {
        $event = $this->createEvent()->daily()->weekdays();
        $this->assertSame('0 0 * * 1-5', $event->getExpression());
    }

    public function test_weekends(): void
    {
        $event = $this->createEvent()->daily()->weekends();
        $this->assertSame('0 0 * * 0,6', $event->getExpression());
    }

    public function test_sundays(): void
    {
        $event = $this->createEvent()->daily()->sundays();
        $this->assertSame('0 0 * * 0', $event->getExpression());
    }

    public function test_mondays(): void
    {
        $event = $this->createEvent()->daily()->mondays();
        $this->assertSame('0 0 * * 1', $event->getExpression());
    }

    public function test_tuesdays(): void
    {
        $event = $this->createEvent()->daily()->tuesdays();
        $this->assertSame('0 0 * * 2', $event->getExpression());
    }

    public function test_wednesdays(): void
    {
        $event = $this->createEvent()->daily()->wednesdays();
        $this->assertSame('0 0 * * 3', $event->getExpression());
    }

    public function test_thursdays(): void
    {
        $event = $this->createEvent()->daily()->thursdays();
        $this->assertSame('0 0 * * 4', $event->getExpression());
    }

    public function test_fridays(): void
    {
        $event = $this->createEvent()->daily()->fridays();
        $this->assertSame('0 0 * * 5', $event->getExpression());
    }

    public function test_saturdays(): void
    {
        $event = $this->createEvent()->daily()->saturdays();
        $this->assertSame('0 0 * * 6', $event->getExpression());
    }

    public function test_days_single(): void
    {
        $event = $this->createEvent()->daily()->days(3);
        $this->assertSame('0 0 * * 3', $event->getExpression());
    }

    public function test_days_multiple(): void
    {
        $event = $this->createEvent()->daily()->days([1, 3, 5]);
        $this->assertSame('0 0 * * 1,3,5', $event->getExpression());
    }

    public function test_custom_cron(): void
    {
        $event = $this->createEvent()->cron('15 10 5 6 1');
        $this->assertSame('15 10 5 6 1', $event->getExpression());
    }

    public function test_fluent_chaining(): void
    {
        $event = $this->createEvent()
            ->daily()
            ->weekdays()
            ->description('Test');

        $this->assertSame('0 0 * * 1-5', $event->getExpression());
        $this->assertSame('Test', $event->getDescription());
    }
}
