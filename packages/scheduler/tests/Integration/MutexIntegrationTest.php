<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Integration;

use Lalaz\Scheduler\Tests\Common\SchedulerIntegrationTestCase;
use Lalaz\Scheduler\Mutex\NullMutex;
use Lalaz\Scheduler\Mutex\MutexInterface;
use Lalaz\Scheduler\Schedule;

/**
 * Integration tests for mutex implementations.
 *
 * Tests lock acquisition, release, and distributed scheduling
 * scenarios using various mutex implementations.
 *
 * @package lalaz/scheduler
 */
class MutexIntegrationTest extends SchedulerIntegrationTestCase
{
    // =========================================================================
    // NullMutex Tests
    // =========================================================================

    public function test_null_mutex_always_acquires_lock(): void
    {
        $mutex = new NullMutex();

        // Should always acquire
        $this->assertTrue($mutex->acquire('test-lock', 3600));
        $this->assertTrue($mutex->acquire('test-lock', 3600));
        $this->assertTrue($mutex->acquire('another-lock', 3600));
    }

    public function test_null_mutex_release_completes(): void
    {
        $mutex = new NullMutex();

        // Release should complete without error (returns void)
        $mutex->release('test-lock');
        $mutex->release('non-existent-lock');

        // After release, should still be able to acquire
        $this->assertTrue($mutex->acquire('test-lock', 3600));
    }

    public function test_null_mutex_exists_always_false(): void
    {
        $mutex = new NullMutex();

        // Lock never "exists" in NullMutex
        $this->assertFalse($mutex->exists('test-lock'));
        $mutex->acquire('test-lock', 3600);
        $this->assertFalse($mutex->exists('test-lock'));
    }

    public function test_null_mutex_suitable_for_single_server(): void
    {
        $schedule = $this->createSchedule(mode: 'single');
        $executed = 0;

        $event = $schedule->call(function () use (&$executed) {
            $executed++;
            return 'done';
        })
            ->everyMinute()
            ->withoutOverlapping();

        // In single mode with NullMutex, overlapping prevention is effectively disabled
        $event->run();
        $event->run();

        $this->assertSame(2, $executed);
    }

    // =========================================================================
    // NullMutex Lifecycle Tests
    // =========================================================================

    public function test_null_mutex_lifecycle(): void
    {
        $mutex = new NullMutex();

        // Lock should not exist initially
        $this->assertFalse($mutex->exists('lifecycle-test'));

        // Acquire lock - always succeeds
        $this->assertTrue($mutex->acquire('lifecycle-test', 3600));

        // Still doesn't "exist" because NullMutex doesn't track
        $this->assertFalse($mutex->exists('lifecycle-test'));

        // Release (no-op)
        $mutex->release('lifecycle-test');

        // Can acquire again
        $this->assertTrue($mutex->acquire('lifecycle-test', 3600));
    }

    // =========================================================================
    // Distributed Mode Configuration Tests
    // =========================================================================

    public function test_distributed_mode_configuration(): void
    {
        $schedule = $this->createSchedule(mode: 'distributed');

        $event = $schedule->call(fn() => 'distributed')
            ->everyMinute()
            ->onOneServer()
            ->description('Distributed task');

        $this->assertRunsOnOneServer($event);
    }

    public function test_one_server_constraint_with_null_mutex(): void
    {
        $mutex = new NullMutex();
        $schedule = new Schedule($mutex, null, true);

        $event = $schedule->call(fn() => 'one server')
            ->everyMinute()
            ->onOneServer();

        $this->assertRunsOnOneServer($event);
    }

    // =========================================================================
    // Concurrent Execution Prevention with NullMutex Tests
    // =========================================================================

    public function test_multiple_events_with_overlapping_prevention(): void
    {
        $schedule = $this->createSchedule();

        $event1 = $schedule->call(fn() => 'task1')
            ->everyMinute()
            ->withoutOverlapping()
            ->description('Task 1');

        $event2 = $schedule->call(fn() => 'task2')
            ->everyMinute()
            ->withoutOverlapping()
            ->description('Task 2');

        $this->assertPreventsOverlapping($event1);
        $this->assertPreventsOverlapping($event2);
    }

    public function test_different_tasks_can_run_concurrently_with_null_mutex(): void
    {
        $mutex = new NullMutex();

        // With NullMutex, all acquisitions succeed
        $this->assertTrue($mutex->acquire('task-1', 3600));
        $this->assertTrue($mutex->acquire('task-2', 3600));
        $this->assertTrue($mutex->acquire('task-3', 3600));

        // Even same task can acquire multiple times
        $this->assertTrue($mutex->acquire('task-1', 3600));
    }

    // =========================================================================
    // Lock Expiration Tests
    // =========================================================================

    public function test_lock_with_custom_expiration(): void
    {
        $mutex = new NullMutex();

        // Short expiration for quick task - should still succeed
        $this->assertTrue($mutex->acquire('quick-task', 60));
    }

    public function test_lock_with_long_expiration(): void
    {
        $mutex = new NullMutex();

        // Long expiration for long-running task - should still succeed
        $this->assertTrue($mutex->acquire('long-task', 86400));
    }

    // =========================================================================
    // Integration with Schedule Tests
    // =========================================================================

    public function test_schedule_uses_provided_mutex(): void
    {
        $mutex = new NullMutex();
        $schedule = new Schedule($mutex);

        $event = $schedule->call(fn() => 'test')
            ->everyMinute()
            ->withoutOverlapping();

        $this->assertInstanceOf(\Lalaz\Scheduler\ScheduledClosure::class, $event);
    }

    public function test_events_in_schedule_can_have_overlapping_prevention(): void
    {
        $schedule = $this->createSchedule();

        $schedule->call(fn() => 'task1')
            ->everyMinute()
            ->withoutOverlapping()
            ->description('Task 1');

        $schedule->call(fn() => 'task2')
            ->everyMinute()
            ->withoutOverlapping()
            ->description('Task 2');

        // Both events should have overlapping prevention
        $this->assertEventCount(2, $schedule);

        foreach ($schedule->events() as $event) {
            $this->assertPreventsOverlapping($event);
        }
    }

    // =========================================================================
    // Real-World Scenario Tests
    // =========================================================================

    public function test_real_world_single_server_cron(): void
    {
        $schedule = $this->createSchedule();
        $executed = [];

        // Typical single-server cron setup
        $schedule->call(function () use (&$executed) {
            $executed[] = 'task1';
            return 'done';
        })->everyMinute()->description('Task 1');

        $schedule->call(function () use (&$executed) {
            $executed[] = 'task2';
            return 'done';
        })->everyMinute()->description('Task 2');

        // Execute due events
        foreach ($schedule->dueEvents() as $event) {
            $event->run();
        }

        $this->assertCount(2, $executed);
    }

    public function test_real_world_backup_job_with_overlap_prevention(): void
    {
        $schedule = $this->createSchedule();

        // Long-running backup job
        $event = $schedule->call(function () {
            // Simulates long-running backup
            return 'backup completed';
        })
            ->everyMinute()
            ->withoutOverlapping(1440) // 24 hour expiration
            ->description('Database backup');

        $this->assertEventCount(1, $schedule);
        $this->assertPreventsOverlapping($event);
    }

    public function test_schedule_with_various_constraints(): void
    {
        $schedule = $this->createSchedule();

        // Event with multiple constraints
        $event = $schedule->call(fn() => 'constrained')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->description('Fully constrained task');

        $this->assertPreventsOverlapping($event);
        $this->assertRunsOnOneServer($event);
    }

    // =========================================================================
    // MutexInterface Implementation Tests
    // =========================================================================

    public function test_null_mutex_implements_interface(): void
    {
        $mutex = new NullMutex();

        $this->assertInstanceOf(MutexInterface::class, $mutex);
    }

    public function test_mutex_interface_methods_exist(): void
    {
        $mutex = new NullMutex();

        $this->assertTrue(method_exists($mutex, 'acquire'));
        $this->assertTrue(method_exists($mutex, 'release'));
        $this->assertTrue(method_exists($mutex, 'exists'));
    }
}
