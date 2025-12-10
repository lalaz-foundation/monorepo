<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Unit\Mutex;

use Lalaz\Scheduler\Tests\Common\SchedulerUnitTestCase;
use Lalaz\Scheduler\Mutex\NullMutex;
use Lalaz\Scheduler\Mutex\MutexInterface;

/**
 * Tests for Mutex implementations.
 *
 * @package lalaz/scheduler
 */
class MutexTest extends SchedulerUnitTestCase
{
    public function test_null_mutex_always_acquires(): void
    {
        $mutex = new NullMutex();

        $this->assertTrue($mutex->acquire('task1', 60));
        $this->assertTrue($mutex->acquire('task1', 60));
        $this->assertTrue($mutex->acquire('task2', 60));
    }

    public function test_null_mutex_never_exists(): void
    {
        $mutex = new NullMutex();

        $mutex->acquire('task1', 60);

        $this->assertFalse($mutex->exists('task1'));
        $this->assertFalse($mutex->exists('task2'));
    }

    public function test_null_mutex_release_is_noop(): void
    {
        $mutex = new NullMutex();

        $mutex->acquire('task1', 60);
        $mutex->release('task1');

        // Should still be able to acquire
        $this->assertTrue($mutex->acquire('task1', 60));
    }

    public function test_null_mutex_implements_interface(): void
    {
        $mutex = new NullMutex();

        $this->assertInstanceOf(MutexInterface::class, $mutex);
    }
}
