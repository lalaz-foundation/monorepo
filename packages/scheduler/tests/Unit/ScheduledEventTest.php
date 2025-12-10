<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Unit;

use Lalaz\Scheduler\Tests\Common\SchedulerUnitTestCase;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Tests for ScheduledEvent class and traits.
 *
 * @package lalaz/scheduler
 */
class ScheduledEventTest extends SchedulerUnitTestCase
{

    public function test_frequency_methods(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        $event->everyMinute();
        $this->assertSame('* * * * *', $event->getExpression());

        $event->everyFiveMinutes();
        $this->assertSame('*/5 * * * *', $event->getExpression());

        $event->everyTenMinutes();
        $this->assertSame('*/10 * * * *', $event->getExpression());

        $event->everyFifteenMinutes();
        $this->assertSame('*/15 * * * *', $event->getExpression());

        $event->everyThirtyMinutes();
        $this->assertSame('*/30 * * * *', $event->getExpression());

        $event->hourly();
        $this->assertSame('0 * * * *', $event->getExpression());

        $event->hourlyAt(30);
        $this->assertSame('30 * * * *', $event->getExpression());

        $event->daily();
        $this->assertSame('0 0 * * *', $event->getExpression());

        $event->dailyAt('13:30');
        $this->assertSame('30 13 * * *', $event->getExpression());

        $event->weekly();
        $this->assertSame('0 0 * * 0', $event->getExpression());

        $event->monthly();
        $this->assertSame('0 0 1 * *', $event->getExpression());

        $event->yearly();
        $this->assertSame('0 0 1 1 *', $event->getExpression());
    }

    public function test_day_constraints(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        $event->daily()->weekdays();
        $this->assertSame('0 0 * * 1-5', $event->getExpression());

        $event->daily()->weekends();
        $this->assertSame('0 0 * * 0,6', $event->getExpression());

        $event->daily()->mondays();
        $this->assertSame('0 0 * * 1', $event->getExpression());

        $event->daily()->fridays();
        $this->assertSame('0 0 * * 5', $event->getExpression());
    }

    public function test_custom_cron_expression(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        $event->cron('15 10 5 * 1');
        $this->assertSame('15 10 5 * 1', $event->getExpression());
    }

    public function test_description(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        $event->description('Test task');
        $this->assertSame('Test task', $event->getDescription());
    }

    public function test_without_overlapping(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        $this->assertFalse($event->preventsOverlapping());

        $event->withoutOverlapping();
        $this->assertTrue($event->preventsOverlapping());

        $event->withoutOverlapping(30);
        $this->assertTrue($event->preventsOverlapping());
    }

    public function test_on_one_server(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        $this->assertFalse($event->runsOnOneServer());

        $event->onOneServer();
        $this->assertTrue($event->runsOnOneServer());
    }

    public function test_run_in_background(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        $this->assertFalse($event->runsInBackground());

        $event->runInBackground();
        $this->assertTrue($event->runsInBackground());
    }

    public function test_is_due(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        // Every minute should always be due
        $event->everyMinute();
        $this->assertTrue($event->isDue());
    }

    public function test_closure_execution(): void
    {
        $executed = false;

        $event = new ScheduledClosure(function () use (&$executed) {
            $executed = true;
        }, $this->mutex, false);

        $event->run();

        $this->assertTrue($executed);
    }

    public function test_when_filter(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        // Should be due by default
        $event->everyMinute();
        $this->assertTrue($event->isDue());

        // Add false filter
        $event->when(false);
        $this->assertFalse($event->isDue());
    }

    public function test_skip_filter(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);

        $event->everyMinute();
        $this->assertTrue($event->isDue());

        // Skip should prevent execution
        $event->skip(true);
        $this->assertFalse($event->isDue());
    }

    public function test_environments_filter(): void
    {
        $event = new ScheduledClosure(fn() => null, $this->mutex, false);
        $event->everyMinute();

        // Default environment check
        $_ENV['APP_ENV'] = 'testing';

        $event->environments(['production']);
        $this->assertFalse($event->isDue());

        $event->environments(['testing']);
        $this->assertTrue($event->isDue());

        unset($_ENV['APP_ENV']);
    }

    public function test_hooks_are_called(): void
    {
        $beforeCalled = false;
        $afterCalled = false;
        $successCalled = false;

        $event = new ScheduledClosure(fn() => 'result', $this->mutex, false);

        $event->before(function () use (&$beforeCalled) {
            $beforeCalled = true;
        });

        $event->after(function () use (&$afterCalled) {
            $afterCalled = true;
        });

        $event->onSuccess(function () use (&$successCalled) {
            $successCalled = true;
        });

        $event->run();

        $this->assertTrue($beforeCalled);
        $this->assertTrue($afterCalled);
        $this->assertTrue($successCalled);
    }

    public function test_failure_hook_on_exception(): void
    {
        $failureCalled = false;
        $capturedException = null;

        $event = new ScheduledClosure(function () {
            throw new \RuntimeException('Test error');
        }, $this->mutex, false);

        $event->onFailure(function ($e) use (&$failureCalled, &$capturedException) {
            $failureCalled = true;
            $capturedException = $e;
        });

        // Should not throw, but call failure callback
        try {
            $event->run();
        } catch (\Throwable) {
            // Expected
        }

        $this->assertTrue($failureCalled);
        $this->assertInstanceOf(\RuntimeException::class, $capturedException);
    }
}
