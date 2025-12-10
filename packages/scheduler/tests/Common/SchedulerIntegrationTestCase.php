<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Common;

use DateTimeImmutable;
use DateTimeZone;
use Lalaz\Scheduler\Schedule;
use Lalaz\Scheduler\ScheduledEvent;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\CronExpression;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Base test case for Scheduler package integration tests.
 *
 * Provides utilities for testing complete scheduling workflows,
 * multi-event scenarios, and real-world scheduling patterns.
 *
 * @package lalaz/scheduler
 */
abstract class SchedulerIntegrationTestCase extends SchedulerUnitTestCase
{
    /**
     * Output directory for test files.
     */
    protected string $outputDir;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary output directory
        $this->outputDir = sys_get_temp_dir() . '/scheduler_test_' . uniqid();
        mkdir($this->outputDir, 0755, true);
    }

    /**
     * Cleanup after tests.
     */
    protected function tearDown(): void
    {
        // Remove temporary directory
        $this->cleanupDirectory($this->outputDir);

        parent::tearDown();
    }

    /**
     * Remove a directory and its contents recursively.
     */
    protected function cleanupDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $files = scandir($path);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $path . DIRECTORY_SEPARATOR . $file;

            if (is_dir($filePath)) {
                $this->cleanupDirectory($filePath);
            } else {
                unlink($filePath);
            }
        }

        rmdir($path);
    }

    // =========================================================================
    // Schedule Building Helpers
    // =========================================================================

    /**
     * Create a schedule with multiple varied events.
     *
     * @return array{schedule: Schedule, events: array}
     */
    protected function createMultiEventSchedule(): array
    {
        $schedule = $this->createSchedule();
        $events = [];

        // Every minute task
        $events['everyMinute'] = $schedule->call(fn() => 'minute')
            ->everyMinute()
            ->description('Every minute task');

        // Hourly task
        $events['hourly'] = $schedule->call(fn() => 'hourly')
            ->hourly()
            ->description('Hourly task');

        // Daily task
        $events['daily'] = $schedule->call(fn() => 'daily')
            ->daily()
            ->description('Daily task');

        // Weekly task
        $events['weekly'] = $schedule->call(fn() => 'weekly')
            ->weekly()
            ->description('Weekly task');

        return ['schedule' => $schedule, 'events' => $events];
    }

    /**
     * Create a schedule with all constraint types.
     *
     * @return array{schedule: Schedule, events: array}
     */
    protected function createConstrainedSchedule(): array
    {
        $schedule = $this->createSchedule();
        $events = [];

        // No overlapping
        $events['noOverlap'] = $schedule->call(fn() => 'no-overlap')
            ->everyMinute()
            ->withoutOverlapping()
            ->description('No overlapping task');

        // One server
        $events['oneServer'] = $schedule->call(fn() => 'one-server')
            ->everyMinute()
            ->onOneServer()
            ->description('One server task');

        // Background
        $events['background'] = $schedule->call(fn() => 'background')
            ->everyMinute()
            ->runInBackground()
            ->description('Background task');

        // Multiple constraints
        $events['multi'] = $schedule->call(fn() => 'multi')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->description('Multi-constraint task');

        return ['schedule' => $schedule, 'events' => $events];
    }

    /**
     * Create a schedule with environment filters.
     *
     * @return array{schedule: Schedule, events: array}
     */
    protected function createFilteredSchedule(): array
    {
        $schedule = $this->createSchedule();
        $events = [];

        // Production only
        $events['production'] = $schedule->call(fn() => 'production')
            ->everyMinute()
            ->production()
            ->description('Production only task');

        // Not in production
        $events['notProduction'] = $schedule->call(fn() => 'not-production')
            ->everyMinute()
            ->exceptProduction()
            ->description('Non-production task');

        // Specific environments
        $events['staging'] = $schedule->call(fn() => 'staging')
            ->everyMinute()
            ->environments(['staging', 'testing'])
            ->description('Staging/testing task');

        // Conditional
        $events['conditional'] = $schedule->call(fn() => 'conditional')
            ->everyMinute()
            ->when(fn() => date('H') < 12)
            ->description('Morning task');

        return ['schedule' => $schedule, 'events' => $events];
    }

    /**
     * Create a schedule with output handling.
     *
     * @return array{schedule: Schedule, events: array, files: array}
     */
    protected function createOutputSchedule(): array
    {
        $schedule = $this->createSchedule();
        $events = [];
        $files = [];

        // File output
        $files['output'] = $this->outputDir . '/output.log';
        $events['fileOutput'] = $schedule->call(fn() => 'file output')
            ->everyMinute()
            ->sendOutputTo($files['output'])
            ->description('File output task');

        // Append output
        $files['append'] = $this->outputDir . '/append.log';
        $events['appendOutput'] = $schedule->call(fn() => 'append output')
            ->everyMinute()
            ->appendOutputTo($files['append'])
            ->description('Append output task');

        return ['schedule' => $schedule, 'events' => $events, 'files' => $files];
    }

    // =========================================================================
    // Event Execution Helpers
    // =========================================================================

    /**
     * Execute all due events and return results.
     *
     * @return array<string, mixed>
     */
    protected function executeDueEvents(Schedule $schedule): array
    {
        $results = [];

        foreach ($schedule->dueEvents() as $event) {
            $key = $event->getDescription() ?? $event->getSummary();
            try {
                $results[$key] = [
                    'success' => true,
                    'result' => $event->run(),
                ];
            } catch (\Throwable $e) {
                $results[$key] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Execute all events (regardless of due status) and return results.
     *
     * @return array<string, mixed>
     */
    protected function executeAllEvents(Schedule $schedule): array
    {
        $results = [];

        foreach ($schedule->events() as $event) {
            $key = $event->getDescription() ?? $event->getSummary();
            try {
                $results[$key] = [
                    'success' => true,
                    'result' => $event->run(),
                ];
            } catch (\Throwable $e) {
                $results[$key] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    // =========================================================================
    // Time Simulation Helpers
    // =========================================================================

    /**
     * Create a DateTime for specific cron testing.
     */
    protected function createDateTimeAt(
        int $minute = 0,
        int $hour = 0,
        int $day = 1,
        int $month = 1,
        int $year = 2024,
        ?int $weekday = null
    ): DateTimeImmutable {
        // If weekday is specified, find the first matching day
        if ($weekday !== null) {
            $date = new DateTimeImmutable(sprintf('%04d-%02d-01 %02d:%02d:00', $year, $month, $hour, $minute));

            while ((int) $date->format('w') !== $weekday) {
                $date = $date->modify('+1 day');
            }

            return $date;
        }

        return new DateTimeImmutable(
            sprintf('%04d-%02d-%02d %02d:%02d:00', $year, $month, $day, $hour, $minute)
        );
    }

    /**
     * Get a DateTime that matches "every minute" expression.
     */
    protected function getEveryMinuteDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable();
    }

    /**
     * Get a DateTime for midnight.
     */
    protected function getMidnightDateTime(): DateTimeImmutable
    {
        return $this->createDateTimeAt(0, 0);
    }

    /**
     * Get a DateTime for specific hour:minute.
     */
    protected function getTimeDateTime(string $time): DateTimeImmutable
    {
        [$hour, $minute] = explode(':', $time);
        return $this->createDateTimeAt((int) $minute, (int) $hour);
    }

    // =========================================================================
    // Callback Tracking
    // =========================================================================

    /**
     * Create an event with full lifecycle tracking.
     *
     * @return array{event: ScheduledClosure, tracker: array}
     */
    protected function createTrackedEvent(?callable $task = null): array
    {
        $tracker = [
            'before' => false,
            'task' => false,
            'after' => false,
            'success' => false,
            'failure' => false,
            'order' => [],
            'result' => null,
            'error' => null,
        ];

        $event = $this->createClosureEvent(function () use (&$tracker, $task) {
            $tracker['task'] = true;
            $tracker['order'][] = 'task';

            if ($task) {
                return $task();
            }

            return 'completed';
        });

        $event->before(function () use (&$tracker) {
            $tracker['before'] = true;
            $tracker['order'][] = 'before';
        });

        $event->after(function ($result) use (&$tracker) {
            $tracker['after'] = true;
            $tracker['order'][] = 'after';
            $tracker['result'] = $result;
        });

        $event->onSuccess(function ($result) use (&$tracker) {
            $tracker['success'] = true;
            $tracker['order'][] = 'success';
        });

        $event->onFailure(function ($e) use (&$tracker) {
            $tracker['failure'] = true;
            $tracker['order'][] = 'failure';
            $tracker['error'] = $e->getMessage();
        });

        return ['event' => $event, 'tracker' => &$tracker];
    }

    // =========================================================================
    // Integration Assertions
    // =========================================================================

    /**
     * Assert that all events in a schedule are valid.
     */
    protected function assertAllEventsValid(Schedule $schedule): void
    {
        foreach ($schedule->events() as $event) {
            $this->assertTrue(
                CronExpression::isValid($event->getExpression()),
                "Event '{$event->getSummary()}' has invalid cron expression: {$event->getExpression()}"
            );
        }
    }

    /**
     * Assert that events execute in proper order.
     */
    protected function assertCallbackOrder(array $expected, array $actual, string $message = ''): void
    {
        $this->assertSame(
            $expected,
            $actual,
            $message ?: 'Callback execution order mismatch'
        );
    }

    /**
     * Assert that a file was created with expected content.
     */
    protected function assertOutputFileContains(string $file, string $expected): void
    {
        $this->assertFileExists($file, "Output file should exist: {$file}");
        $content = file_get_contents($file);
        $this->assertStringContainsString($expected, $content);
    }

    /**
     * Assert that execution results match expectations.
     *
     * @param array<string, bool> $expected Map of task description to success/failure
     * @param array<string, array> $results Actual execution results
     */
    protected function assertExecutionResults(array $expected, array $results): void
    {
        foreach ($expected as $key => $shouldSucceed) {
            $this->assertArrayHasKey($key, $results, "Missing result for: {$key}");
            $this->assertSame(
                $shouldSucceed,
                $results[$key]['success'],
                "Unexpected result for '{$key}'"
            );
        }
    }

    /**
     * Assert event has a specific description.
     */
    protected function assertEventHasDescription(ScheduledEvent $event, string $description): void
    {
        $this->assertSame($description, $event->getDescription());
    }
}
