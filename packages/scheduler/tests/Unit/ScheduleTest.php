<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Unit;

use Lalaz\Scheduler\Tests\Common\SchedulerUnitTestCase;
use Lalaz\Scheduler\Schedule;
use Lalaz\Scheduler\ScheduledCommand;
use Lalaz\Scheduler\ScheduledJob;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Tests for Schedule class.
 *
 * @package lalaz/scheduler
 */
class ScheduleTest extends SchedulerUnitTestCase
{
    private Schedule $schedule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedule = $this->createSchedule();
    }

    public function test_can_schedule_a_command(): void
    {
        $event = $this->schedule->command('test:command');

        $this->assertInstanceOf(ScheduledCommand::class, $event);
        $this->assertCount(1, $this->schedule->events());
    }

    public function test_can_schedule_a_job(): void
    {
        $event = $this->schedule->job(new \stdClass());

        $this->assertInstanceOf(ScheduledJob::class, $event);
        $this->assertCount(1, $this->schedule->events());
    }

    public function test_can_schedule_a_closure(): void
    {
        $event = $this->schedule->call(function () {
            return 'test';
        });

        $this->assertInstanceOf(ScheduledClosure::class, $event);
        $this->assertCount(1, $this->schedule->events());
    }

    public function test_can_set_timezone(): void
    {
        $this->schedule->timezone('America/New_York');

        $event = $this->schedule->command('test:command');

        $this->assertInstanceOf(ScheduledCommand::class, $event);
    }

    public function test_events_returns_all_scheduled_events(): void
    {
        $this->schedule->command('command1');
        $this->schedule->command('command2');
        $this->schedule->job(new \stdClass());
        $this->schedule->call(fn() => null);

        $this->assertCount(4, $this->schedule->events());
    }

    public function test_due_events_filters_by_schedule(): void
    {
        // This event is due every minute
        $this->schedule->command('due:command')
            ->everyMinute();

        // This event runs at midnight only
        $this->schedule->command('not:due')
            ->dailyAt('03:00');

        $dueEvents = $this->schedule->dueEvents();

        // At least one should be due (the every minute one)
        $this->assertGreaterThanOrEqual(1, count($dueEvents));

        $names = array_map(fn($e) => $e->getSummary(), $dueEvents);
        $this->assertContains('due:command', $names);
    }

    public function test_command_with_arguments(): void
    {
        $event = $this->schedule->command('test:command --flag --option=value');

        $this->assertInstanceOf(ScheduledCommand::class, $event);
        $this->assertSame('test:command --flag --option=value', $event->getSummary());
    }

    public function test_command_with_array_arguments(): void
    {
        $event = $this->schedule->command('test:command', ['--flag', '--option=value']);

        $this->assertInstanceOf(ScheduledCommand::class, $event);
    }

    public function test_schedule_fluent_api(): void
    {
        $event = $this->schedule->command('test:command')
            ->daily()
            ->description('Test description')
            ->withoutOverlapping();

        $this->assertSame('0 0 * * *', $event->getExpression());
        $this->assertSame('Test description', $event->getDescription());
        $this->assertTrue($event->preventsOverlapping());
    }
}
