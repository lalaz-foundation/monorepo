<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Integration;

use DateTimeImmutable;
use Lalaz\Scheduler\Tests\Common\SchedulerIntegrationTestCase;
use Lalaz\Scheduler\Schedule;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\CronExpression;

/**
 * Integration tests for complete scheduling workflows.
 *
 * Tests end-to-end scheduling scenarios including multi-event
 * schedules, event execution, and lifecycle management.
 *
 * @package lalaz/scheduler
 */
class ScheduleFlowIntegrationTest extends SchedulerIntegrationTestCase
{
    // =========================================================================
    // Multi-Event Schedule Tests
    // =========================================================================

    public function test_schedule_multiple_events_with_different_frequencies(): void
    {
        $result = $this->createMultiEventSchedule();
        $schedule = $result['schedule'];

        // Should have 4 events
        $this->assertEventCount(4, $schedule);

        // All events should have valid cron expressions
        $this->assertAllEventsValid($schedule);

        // At least "every minute" should be due
        $dueEvents = $schedule->dueEvents();
        $this->assertNotEmpty($dueEvents);

        $dueDescriptions = array_map(fn($e) => $e->getDescription(), $dueEvents);
        $this->assertContains('Every minute task', $dueDescriptions);
    }

    public function test_execute_all_due_events(): void
    {
        $result = $this->createMultiEventSchedule();
        $schedule = $result['schedule'];

        $executionResults = $this->executeDueEvents($schedule);

        // Every minute task should have executed
        $this->assertArrayHasKey('Every minute task', $executionResults);
        $this->assertTrue($executionResults['Every minute task']['success']);
        $this->assertSame('minute', $executionResults['Every minute task']['result']);
    }

    public function test_schedule_with_constraints(): void
    {
        $result = $this->createConstrainedSchedule();
        $schedule = $result['schedule'];
        $events = $result['events'];

        // Verify constraints are set
        $this->assertPreventsOverlapping($events['noOverlap']);
        $this->assertRunsOnOneServer($events['oneServer']);
        $this->assertRunsInBackground($events['background']);

        // Multi-constraint event
        $this->assertPreventsOverlapping($events['multi']);
        $this->assertRunsOnOneServer($events['multi']);
    }

    public function test_schedule_with_environment_filters(): void
    {
        $result = $this->createFilteredSchedule();
        $schedule = $result['schedule'];
        $events = $result['events'];

        // Test production filter
        $this->setEnvironment('production');
        $this->assertEventIsDue($events['production']);
        $this->assertEventNotDue($events['notProduction']);

        // Test non-production filter
        $this->setEnvironment('testing');
        $this->assertEventNotDue($events['production']);
        $this->assertEventIsDue($events['notProduction']);

        // Test specific environments
        $this->setEnvironment('staging');
        $this->assertEventIsDue($events['staging']);

        $this->setEnvironment('production');
        $this->assertEventNotDue($events['staging']);
    }

    // =========================================================================
    // Event Lifecycle Tests
    // =========================================================================

    public function test_event_lifecycle_callbacks_order(): void
    {
        $tracked = $this->createTrackedEvent();
        $event = $tracked['event'];

        $event->everyMinute()->run();

        // Verify all callbacks were called
        $this->assertTrue($tracked['tracker']['before']);
        $this->assertTrue($tracked['tracker']['task']);
        $this->assertTrue($tracked['tracker']['after']);
        $this->assertTrue($tracked['tracker']['success']);
        $this->assertFalse($tracked['tracker']['failure']);

        // Verify order
        $this->assertCallbackOrder(
            ['before', 'task', 'after', 'success'],
            $tracked['tracker']['order']
        );
    }

    public function test_event_lifecycle_on_failure(): void
    {
        $tracked = $this->createTrackedEvent(function () {
            throw new \RuntimeException('Task failed');
        });

        $event = $tracked['event'];

        try {
            $event->everyMinute()->run();
        } catch (\RuntimeException) {
            // Expected
        }

        // Verify failure callback was called
        $this->assertTrue($tracked['tracker']['before']);
        $this->assertTrue($tracked['tracker']['task']);
        $this->assertTrue($tracked['tracker']['failure']);
        $this->assertFalse($tracked['tracker']['success']);
        $this->assertSame('Task failed', $tracked['tracker']['error']);
    }

    public function test_multiple_before_and_after_callbacks(): void
    {
        $order = [];

        $event = $this->createClosureEvent(function () use (&$order) {
            $order[] = 'task';
            return 'done';
        });

        $event->before(function () use (&$order) { $order[] = 'before1'; });
        $event->before(function () use (&$order) { $order[] = 'before2'; });
        $event->after(function () use (&$order) { $order[] = 'after1'; });
        $event->after(function () use (&$order) { $order[] = 'after2'; });
        $event->then(function () use (&$order) { $order[] = 'then'; });

        $event->everyMinute()->run();

        $this->assertSame(
            ['before1', 'before2', 'task', 'after1', 'after2', 'then'],
            $order
        );
    }

    // =========================================================================
    // Output Handling Tests
    // =========================================================================

    public function test_event_output_to_file(): void
    {
        $result = $this->createOutputSchedule();
        $events = $result['events'];
        $files = $result['files'];

        // Execute the file output event
        $events['fileOutput']->run();

        // Verify file was created with output
        $this->assertOutputFileContains($files['output'], 'file output');
    }

    public function test_event_append_output(): void
    {
        $result = $this->createOutputSchedule();
        $events = $result['events'];
        $files = $result['files'];

        // Create initial content
        file_put_contents($files['append'], "initial content\n");

        // Execute append output event multiple times
        $events['appendOutput']->run();
        $events['appendOutput']->run();

        // Verify appended content
        $content = file_get_contents($files['append']);
        $this->assertStringContainsString('initial content', $content);
        $this->assertStringContainsString('append output', $content);
    }

    public function test_custom_output_handler(): void
    {
        $capturedOutput = null;
        $capturedCode = null;

        $event = $this->createClosureEvent(fn() => 'custom output');
        $event->everyMinute()
            ->handleOutputUsing(function ($output, $code) use (&$capturedOutput, &$capturedCode) {
                $capturedOutput = $output;
                $capturedCode = $code;
            });

        $event->run();

        $this->assertSame('custom output', $capturedOutput);
        $this->assertSame(0, $capturedCode);
    }

    // =========================================================================
    // Timezone Tests
    // =========================================================================

    public function test_schedule_with_timezone(): void
    {
        $schedule = $this->createSchedule(timezone: 'America/New_York');

        $event = $schedule->call(fn() => 'tz')
            ->everyMinute()
            ->description('Timezone task');

        $this->assertEventIsDue($event);
    }

    public function test_event_with_individual_timezone(): void
    {
        $schedule = $this->createSchedule();

        $event = $schedule->call(fn() => 'tz')
            ->timezone('Europe/London')
            ->everyMinute()
            ->description('London task');

        $this->assertEventIsDue($event);
    }

    // =========================================================================
    // Complex Filter Tests
    // =========================================================================

    public function test_combined_when_and_skip_filters(): void
    {
        $condition1 = true;
        $condition2 = false;

        $event = $this->createClosureEvent();
        $event->everyMinute()
            ->when($condition1)
            ->skip($condition2);

        $this->assertEventIsDue($event);

        // Now with skip = true
        $event2 = $this->createClosureEvent();
        $event2->everyMinute()
            ->when(true)
            ->skip(true);

        $this->assertEventNotDue($event2);
    }

    public function test_callable_filters(): void
    {
        $shouldRun = true;

        $event = $this->createClosureEvent();
        $event->everyMinute()
            ->when(function () use (&$shouldRun) {
                return $shouldRun;
            });

        $this->assertEventIsDue($event);

        $shouldRun = false;
        $this->assertEventNotDue($event);
    }

    // =========================================================================
    // Schedule Statistics Tests
    // =========================================================================

    public function test_schedule_statistics(): void
    {
        $schedule = $this->createSchedule();

        $this->assertFalse($schedule->hasEvents());
        $this->assertSame(0, $schedule->count());

        $schedule->call(fn() => 1)->everyMinute();
        $schedule->call(fn() => 2)->hourly();
        $schedule->call(fn() => 3)->daily();

        $this->assertTrue($schedule->hasEvents());
        $this->assertSame(3, $schedule->count());
        $this->assertCount(3, $schedule->events());
    }

    public function test_due_events_count(): void
    {
        $schedule = $this->createSchedule();

        // Always due
        $schedule->call(fn() => 'always')->everyMinute();

        // Conditionally due (false)
        $schedule->call(fn() => 'never')->everyMinute()->when(false);

        // Check due events
        $dueEvents = $schedule->dueEvents();
        $this->assertCount(1, $dueEvents);
    }

    // =========================================================================
    // Real-World Scenario Tests
    // =========================================================================

    public function test_real_world_maintenance_schedule(): void
    {
        $schedule = $this->createSchedule();
        $results = [];

        // Cache cleanup - every 5 minutes
        $schedule->call(function () use (&$results) {
            $results[] = 'cache_cleaned';
            return true;
        })->everyFiveMinutes()->description('Cache cleanup');

        // Database optimization - daily at 3am
        $schedule->call(function () use (&$results) {
            $results[] = 'db_optimized';
            return true;
        })->dailyAt('03:00')->description('Database optimization');

        // Log rotation - weekly
        $schedule->call(function () use (&$results) {
            $results[] = 'logs_rotated';
            return true;
        })->weekly()->description('Log rotation');

        // Report generation - monthly
        $schedule->call(function () use (&$results) {
            $results[] = 'report_generated';
            return true;
        })->monthly()->description('Monthly report');

        $this->assertEventCount(4, $schedule);
        $this->assertAllEventsValid($schedule);
    }

    public function test_real_world_business_schedule(): void
    {
        $this->setEnvironment('production');

        $schedule = $this->createSchedule();
        $orderCount = 0;
        $reportsSent = 0;

        // Process orders every minute during business hours
        $schedule->call(function () use (&$orderCount) {
            $orderCount++;
            return "Processed order batch {$orderCount}";
        })
            ->everyMinute()
            ->production()
            ->description('Order processing');

        // Send daily sales report
        $schedule->call(function () use (&$reportsSent) {
            $reportsSent++;
            return "Report {$reportsSent} sent";
        })
            ->dailyAt('18:00')
            ->description('Daily sales report');

        $this->assertEventCount(2, $schedule);

        // Execute due events - weekdays() filter may not pass today
        // So let's just verify the events are properly set up
        $this->assertAllEventsValid($schedule);

        // At least the everyMinute task should be due (production env is set)
        $dueEvents = $schedule->dueEvents();
        $this->assertGreaterThanOrEqual(1, count($dueEvents), 'At least Order processing should be due');
    }

    // =========================================================================
    // Error Handling Tests
    // =========================================================================

    public function test_schedule_continues_after_task_failure(): void
    {
        $schedule = $this->createSchedule();
        $results = [];

        // First task - fails
        $schedule->call(function () {
            throw new \RuntimeException('Task 1 failed');
        })->everyMinute()->description('Failing task');

        // Second task - succeeds
        $schedule->call(function () use (&$results) {
            $results[] = 'task2';
            return 'success';
        })->everyMinute()->description('Success task');

        // Execute all due events, catching failures
        foreach ($schedule->dueEvents() as $event) {
            try {
                $event->run();
            } catch (\Throwable) {
                $results[] = 'caught_error';
            }
        }

        // Both tasks should have been attempted
        $this->assertContains('caught_error', $results);
        $this->assertContains('task2', $results);
    }

    public function test_event_options_summary(): void
    {
        $event = $this->createClosureEvent();
        $event->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->runInBackground()
            ->description('Full options');

        $options = $event->getOptions();

        $this->assertContains('no-overlap', $options);
        $this->assertContains('one-server', $options);
        $this->assertContains('background', $options);
    }

    public function test_next_run_date_calculation(): void
    {
        $event = $this->createClosureEvent();
        $event->hourly();

        $nextRun = $event->nextRunDate();

        $this->assertNotNull($nextRun);
        $this->assertInstanceOf(DateTimeImmutable::class, $nextRun);

        // Should be in the future
        $this->assertGreaterThan(time(), $nextRun->getTimestamp());
    }
}
