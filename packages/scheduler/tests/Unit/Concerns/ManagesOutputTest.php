<?php declare(strict_types=1);

namespace Lalaz\Scheduler\Tests\Unit\Concerns;

use Lalaz\Scheduler\Tests\Common\SchedulerUnitTestCase;
use Lalaz\Scheduler\ScheduledClosure;
use Lalaz\Scheduler\Mutex\NullMutex;

/**
 * Tests for ManagesOutput trait.
 *
 * @package lalaz/scheduler
 */
class ManagesOutputTest extends SchedulerUnitTestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = sys_get_temp_dir() . '/scheduler_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
        parent::tearDown();
    }

    private function createEvent(?callable $callback = null): ScheduledClosure
    {
        return $this->createClosureEvent($callback ?? fn() => 'output');
    }

    public function test_before_callback_is_called(): void
    {
        $called = false;

        $event = $this->createEvent()
            ->before(function () use (&$called) {
                $called = true;
            });

        $event->run();

        $this->assertTrue($called);
    }

    public function test_after_callback_is_called(): void
    {
        $called = false;

        $event = $this->createEvent()
            ->after(function () use (&$called) {
                $called = true;
            });

        $event->run();

        $this->assertTrue($called);
    }

    public function test_then_is_alias_for_after(): void
    {
        $called = false;

        $event = $this->createEvent()
            ->then(function () use (&$called) {
                $called = true;
            });

        $event->run();

        $this->assertTrue($called);
    }

    public function test_on_success_callback_on_successful_execution(): void
    {
        $called = false;

        $event = $this->createEvent()
            ->onSuccess(function () use (&$called) {
                $called = true;
            });

        $event->run();

        $this->assertTrue($called);
    }

    public function test_on_failure_callback_on_exception(): void
    {
        $called = false;
        $capturedException = null;

        $event = $this->createEvent(function () {
            throw new \RuntimeException('Test error');
        })
            ->onFailure(function ($e) use (&$called, &$capturedException) {
                $called = true;
                $capturedException = $e;
            });

        try {
            $event->run();
        } catch (\Throwable) {
            // Expected
        }

        $this->assertTrue($called);
        $this->assertInstanceOf(\RuntimeException::class, $capturedException);
        $this->assertSame('Test error', $capturedException->getMessage());
    }

    public function test_multiple_before_callbacks(): void
    {
        $order = [];

        $event = $this->createEvent()
            ->before(function () use (&$order) {
                $order[] = 'first';
            })
            ->before(function () use (&$order) {
                $order[] = 'second';
            });

        $event->run();

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_multiple_after_callbacks(): void
    {
        $order = [];

        $event = $this->createEvent()
            ->after(function () use (&$order) {
                $order[] = 'first';
            })
            ->after(function () use (&$order) {
                $order[] = 'second';
            });

        $event->run();

        $this->assertSame(['first', 'second'], $order);
    }

    public function test_callback_order(): void
    {
        $order = [];

        $event = $this->createEvent(function () use (&$order) {
            $order[] = 'task';
            return 'result';
        })
            ->before(function () use (&$order) {
                $order[] = 'before';
            })
            ->after(function () use (&$order) {
                $order[] = 'after';
            })
            ->onSuccess(function () use (&$order) {
                $order[] = 'success';
            });

        $event->run();

        $this->assertSame(['before', 'task', 'after', 'success'], $order);
    }

    public function test_send_output_to_creates_file(): void
    {
        $event = $this->createEvent(fn() => 'test output')
            ->sendOutputTo($this->tempFile);

        $event->run();

        $this->assertFileExists($this->tempFile);
        $this->assertStringContainsString('test output', file_get_contents($this->tempFile));
    }

    public function test_append_output_to(): void
    {
        file_put_contents($this->tempFile, "first\n");

        $event = $this->createEvent(fn() => 'second')
            ->appendOutputTo($this->tempFile);

        $event->run();

        $content = file_get_contents($this->tempFile);
        $this->assertStringContainsString('first', $content);
        $this->assertStringContainsString('second', $content);
    }

    public function test_handle_output_using_callback(): void
    {
        $capturedOutput = null;
        $capturedExitCode = null;

        $event = $this->createEvent(fn() => 'callback output')
            ->handleOutputUsing(function ($output, $exitCode) use (&$capturedOutput, &$capturedExitCode) {
                $capturedOutput = $output;
                $capturedExitCode = $exitCode;
            });

        $event->run();

        $this->assertSame('callback output', $capturedOutput);
        $this->assertSame(0, $capturedExitCode);
    }
}
