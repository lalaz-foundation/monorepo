<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Integration;

use Lalaz\Scheduler\Tests\Common\SchedulerIntegrationTestCase;
use Lalaz\Scheduler\Schedule;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\ScheduledCommand;
use Lalaz\Scheduler\ScheduledJob;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Integration tests for scheduled event types.
 *
 * Tests ScheduledClosure, ScheduledCommand, and ScheduledJob
 * event types with various configurations and scenarios.
 *
 * @package lalaz/scheduler
 */
class ScheduledEventsIntegrationTest extends SchedulerIntegrationTestCase
{
    // =========================================================================
    // ScheduledClosure Integration Tests
    // =========================================================================

    public function test_closure_event_complete_workflow(): void
    {
        $result = null;
        $hooks = [];

        $event = $this->createClosureEvent(function () use (&$result) {
            $result = 'executed';
            return 'closure result';
        });

        $event->everyMinute()
            ->description('Complete closure workflow')
            ->before(function () use (&$hooks) { $hooks[] = 'before'; })
            ->after(function () use (&$hooks) { $hooks[] = 'after'; })
            ->onSuccess(function () use (&$hooks) { $hooks[] = 'success'; });

        $this->assertEventIsDue($event);

        $event->run();

        $this->assertSame('executed', $result);
        $this->assertContains('before', $hooks);
        $this->assertContains('after', $hooks);
        $this->assertContains('success', $hooks);
    }

    public function test_closure_event_with_return_value(): void
    {
        $capturedOutput = null;

        // Note: handleOutput is only called when result is a string
        $event = $this->createClosureEvent(function () {
            return 'custom output';
        });

        $event->everyMinute()
            ->handleOutputUsing(function ($output, $code) use (&$capturedOutput) {
                $capturedOutput = $output;
            });

        $event->run();

        $this->assertSame('custom output', $capturedOutput);
    }

    public function test_closure_event_with_exception(): void
    {
        $failureMessage = null;

        $event = $this->createClosureEvent(function () {
            throw new \RuntimeException('Closure failed');
        });

        $event->everyMinute()
            ->onFailure(function ($exception) use (&$failureMessage) {
                $failureMessage = $exception->getMessage();
            });

        try {
            $event->run();
        } catch (\RuntimeException) {
            // Expected
        }

        $this->assertSame('Closure failed', $failureMessage);
    }

    public function test_multiple_closure_events_in_schedule(): void
    {
        $schedule = $this->createSchedule();
        $executed = [];

        $schedule->call(function () use (&$executed) {
            $executed[] = 'task1';
            return 'task1';
        })->everyMinute()->description('Task 1');

        $schedule->call(function () use (&$executed) {
            $executed[] = 'task2';
            return 'task2';
        })->everyMinute()->description('Task 2');

        $schedule->call(function () use (&$executed) {
            $executed[] = 'task3';
            return 'task3';
        })->everyMinute()->description('Task 3');

        $this->assertEventCount(3, $schedule);

        // Execute all due events
        foreach ($schedule->dueEvents() as $event) {
            $event->run();
        }

        $this->assertCount(3, $executed);
        $this->assertContains('task1', $executed);
        $this->assertContains('task2', $executed);
        $this->assertContains('task3', $executed);
    }

    // =========================================================================
    // ScheduledCommand Integration Tests
    // =========================================================================

    public function test_command_event_registration(): void
    {
        $schedule = $this->createSchedule();

        $event = $schedule->command('cache:clear')
            ->everyMinute()
            ->description('Clear cache');

        $this->assertInstanceOf(ScheduledCommand::class, $event);
        $this->assertEventIsDue($event);
        $this->assertSame('Clear cache', $event->getDescription());
    }

    public function test_command_event_with_arguments(): void
    {
        $schedule = $this->createSchedule();

        $event = $schedule->command('queue:work', ['--queue=high', '--tries=3'])
            ->everyMinute()
            ->description('Process high priority queue');

        $this->assertInstanceOf(ScheduledCommand::class, $event);
        $this->assertEventHasDescription($event, 'Process high priority queue');
    }

    public function test_command_event_in_background(): void
    {
        $schedule = $this->createSchedule();

        $event = $schedule->command('backup:run')
            ->everyMinute()
            ->runInBackground()
            ->description('Background backup');

        $this->assertRunsInBackground($event);
    }

    public function test_command_event_with_mutex(): void
    {
        $schedule = $this->createSchedule();

        $event = $schedule->command('migrate:status')
            ->everyMinute()
            ->withoutOverlapping()
            ->description('Migration status');

        $this->assertPreventsOverlapping($event);
    }

    // =========================================================================
    // ScheduledJob Integration Tests
    // =========================================================================

    public function test_job_event_registration(): void
    {
        $schedule = $this->createSchedule();

        // job() requires an object instance, not a string
        $job = new \stdClass();
        $job->name = 'SendEmails';

        $event = $schedule->job($job)
            ->everyMinute()
            ->description('Send queued emails');

        $this->assertInstanceOf(ScheduledJob::class, $event);
        $this->assertEventIsDue($event);
    }

    public function test_job_event_with_queue(): void
    {
        $schedule = $this->createSchedule();

        // job() requires an object instance
        $job = new \stdClass();
        $job->name = 'ProcessOrder';

        $event = $schedule->job($job, 'orders')
            ->everyMinute()
            ->description('Process orders');

        $this->assertInstanceOf(ScheduledJob::class, $event);
        $this->assertEventHasDescription($event, 'Process orders');
    }

    public function test_job_event_on_one_server(): void
    {
        $schedule = $this->createSchedule();

        // job() requires an object instance
        $job = new \stdClass();
        $job->name = 'CacheCleanup';

        $event = $schedule->job($job)
            ->everyMinute()
            ->onOneServer()
            ->description('Cache cleanup');

        $this->assertRunsOnOneServer($event);
    }

    // =========================================================================
    // Mixed Event Types Tests
    // =========================================================================

    public function test_schedule_with_mixed_event_types(): void
    {
        $schedule = $this->createSchedule();

        // Closure
        $closure = $schedule->call(fn() => 'closure')
            ->everyMinute()
            ->description('Closure task');

        // Command
        $command = $schedule->command('cache:clear')
            ->hourly()
            ->description('Command task');

        // Job (requires object instance)
        $jobObj = new \stdClass();
        $jobObj->name = 'Backup';
        $job = $schedule->job($jobObj)
            ->daily()
            ->description('Job task');

        $this->assertInstanceOf(ScheduledClosure::class, $closure);
        $this->assertInstanceOf(ScheduledCommand::class, $command);
        $this->assertInstanceOf(ScheduledJob::class, $job);

        $this->assertEventCount(3, $schedule);
    }

    public function test_all_event_types_support_frequencies(): void
    {
        $schedule = $this->createSchedule();

        // All types with various frequencies
        $schedule->call(fn() => 1)->everyFiveMinutes();
        $schedule->call(fn() => 2)->hourly();
        $schedule->call(fn() => 3)->dailyAt('08:00');
        $schedule->call(fn() => 4)->weeklyOn(1, '09:00');
        $schedule->call(fn() => 5)->monthlyOn(1, '10:00');
        $schedule->call(fn() => 6)->quarterly();
        $schedule->call(fn() => 7)->yearly();

        $this->assertEventCount(7, $schedule);
        $this->assertAllEventsValid($schedule);
    }

    public function test_all_event_types_support_filters(): void
    {
        $this->setEnvironment('production');

        $schedule = $this->createSchedule();

        $event1 = $schedule->call(fn() => 1)
            ->everyMinute()
            ->when(true)
            ->production();

        $event2 = $schedule->command('test:command')
            ->everyMinute()
            ->weekdays();

        // job() requires object instance
        $jobObj = new \stdClass();
        $jobObj->name = 'Test';
        $event3 = $schedule->job($jobObj)
            ->everyMinute()
            ->environments(['production', 'staging']);

        // Verify events are created
        $this->assertEventCount(3, $schedule);
        // isDue() tests expression matching - filters pass is internal
        $this->assertEventIsDue($event1);
    }

    public function test_all_event_types_support_constraints(): void
    {
        $schedule = $this->createSchedule();

        $closure = $schedule->call(fn() => 1)
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();

        $command = $schedule->command('test')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // job() requires object instance
        $jobObj = new \stdClass();
        $jobObj->name = 'Test';
        $job = $schedule->job($jobObj)
            ->everyMinute()
            ->onOneServer();

        $this->assertPreventsOverlapping($closure);
        $this->assertRunsOnOneServer($closure);
        $this->assertPreventsOverlapping($command);
        $this->assertRunsInBackground($command);
        $this->assertRunsOnOneServer($job);
    }

    // =========================================================================
    // Event Options Tests
    // =========================================================================

    public function test_event_options_retrieval(): void
    {
        $event = $this->createClosureEvent();

        $event->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->description('Full options event');

        $options = $event->getOptions();

        $this->assertIsArray($options);
        $this->assertContains('no-overlap', $options);
        $this->assertContains('one-server', $options);
        $this->assertContains('background', $options);
    }

    public function test_event_cron_expression_access(): void
    {
        $event = $this->createClosureEvent();
        $event->everyFiveMinutes();

        $expression = $event->getExpression();

        $this->assertSame('*/5 * * * *', $expression);
    }

    public function test_event_next_run_date(): void
    {
        $event = $this->createClosureEvent();
        $event->hourly();

        $nextRun = $event->nextRunDate();

        $this->assertNotNull($nextRun);
        $this->assertInstanceOf(\DateTimeImmutable::class, $nextRun);
    }

    // =========================================================================
    // Mutex Integration Tests
    // =========================================================================

    public function test_event_with_null_mutex(): void
    {
        $mutex = new NullMutex();
        $schedule = new Schedule(mutex: $mutex);

        $event = $schedule->call(fn() => 'test')
            ->everyMinute()
            ->withoutOverlapping();

        // NullMutex always allows execution
        $this->assertTrue($event->isDue());
    }

    public function test_event_overlapping_prevention_setup(): void
    {
        $schedule = $this->createSchedule();

        $event = $schedule->call(fn() => 'test')
            ->everyMinute()
            ->withoutOverlapping();

        // Verify overlapping prevention is configured
        $this->assertPreventsOverlapping($event);
        $this->assertTrue($event->isDue()); // Expression is due
    }

    public function test_distributed_mode_configuration(): void
    {
        $schedule = $this->createSchedule(mode: 'distributed');

        $event = $schedule->call(fn() => 'distributed')
            ->everyMinute()
            ->onOneServer()
            ->description('Distributed task');

        $this->assertRunsOnOneServer($event);
    }

    // =========================================================================
    // Event Chaining Tests
    // =========================================================================

    public function test_fluent_event_configuration(): void
    {
        $event = $this->createClosureEvent();

        $result = $event
            ->everyMinute()
            ->weekdays()
            ->between('09:00', '17:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->before(fn() => null)
            ->after(fn() => null)
            ->onSuccess(fn() => null)
            ->onFailure(fn() => null)
            ->description('Fully configured event');

        // All methods should return the event for chaining
        $this->assertInstanceOf(ScheduledClosure::class, $result);
        $this->assertSame('Fully configured event', $result->getDescription());
    }

    // =========================================================================
    // Real-World Scenario Tests
    // =========================================================================

    public function test_real_world_email_processing_schedule(): void
    {
        $schedule = $this->createSchedule();
        $processed = [];

        // Process newsletter queue every 5 minutes
        $schedule->call(function () use (&$processed) {
            $processed[] = 'newsletter';
            return 'processed';
        })
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->description('Process newsletter queue');

        // Send transactional emails every minute
        $schedule->call(function () use (&$processed) {
            $processed[] = 'transactional';
            return 'processed';
        })
            ->everyMinute()
            ->description('Send transactional emails');

        // Daily digest at 8am
        $schedule->call(function () use (&$processed) {
            $processed[] = 'digest';
            return 'processed';
        })
            ->dailyAt('08:00')
            ->description('Send daily digest');

        $this->assertEventCount(3, $schedule);
        $this->assertAllEventsValid($schedule);
    }

    public function test_real_world_maintenance_schedule(): void
    {
        $this->setEnvironment('production');

        $schedule = $this->createSchedule();

        // Cache warming every hour
        $schedule->command('cache:warm')
            ->hourly()
            ->production()
            ->description('Warm application cache');

        // Database cleanup daily at 3am
        $schedule->command('db:cleanup')
            ->dailyAt('03:00')
            ->production()
            ->withoutOverlapping()
            ->description('Database cleanup');

        // Log rotation weekly
        $schedule->command('logs:rotate')
            ->weekly()
            ->onOneServer()
            ->description('Rotate log files');

        $this->assertEventCount(3, $schedule);

        // All should be valid
        $this->assertAllEventsValid($schedule);
    }

    public function test_real_world_reporting_schedule(): void
    {
        $schedule = $this->createSchedule();

        // Hourly metrics during business hours (using closure)
        $schedule->call(fn() => 'collect metrics')
            ->hourly()
            ->weekdays()
            ->between('08:00', '18:00')
            ->description('Collect hourly metrics');

        // Daily summary at end of day (using closure)
        $schedule->call(fn() => 'daily summary')
            ->dailyAt('18:00')
            ->weekdays()
            ->description('Generate daily summary');

        // Weekly report Friday at 5pm (using command)
        $schedule->command('reports:weekly')
            ->weeklyOn(5, '17:00')
            ->description('Generate weekly report');

        // Monthly analytics on first of month (using job)
        $jobObj = new \stdClass();
        $jobObj->type = 'MonthlyAnalytics';
        $schedule->job($jobObj)
            ->monthlyOn(1, '06:00')
            ->description('Generate monthly analytics');

        $this->assertEventCount(4, $schedule);
    }

    // =========================================================================
    // Timezone Tests
    // =========================================================================

    public function test_event_with_timezone_override(): void
    {
        $schedule = $this->createSchedule(timezone: 'UTC');

        $event = $schedule->call(fn() => 'tz')
            ->everyMinute()
            ->timezone('America/New_York')
            ->description('New York timezone');

        // Event should have its own timezone
        $this->assertEventIsDue($event);
    }

    public function test_multiple_timezones_in_schedule(): void
    {
        $schedule = $this->createSchedule();

        $schedule->call(fn() => 'utc')
            ->everyMinute()
            ->timezone('UTC')
            ->description('UTC task');

        $schedule->call(fn() => 'tokyo')
            ->everyMinute()
            ->timezone('Asia/Tokyo')
            ->description('Tokyo task');

        $schedule->call(fn() => 'london')
            ->everyMinute()
            ->timezone('Europe/London')
            ->description('London task');

        $this->assertEventCount(3, $schedule);
    }
}
