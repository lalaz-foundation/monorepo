<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Resilience;

use Lalaz\Support\Resilience\Retry;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use InvalidArgumentException;
use Throwable;

#[CoversClass(Retry::class)]
/**
 * Tests for the Retry class.
 */
final class RetryTest extends FrameworkUnitTestCase
{
    public function testretriesFailingCallbacksTheConfiguredNumberOfTimes(): void
    {
        $attempts = 0;
        $exception = new RuntimeException('fail');

        try {
            Retry::times(3)
                ->withoutJitter()
                ->withDelay(0)
                ->execute(function () use (&$attempts, $exception) {
                    $attempts++;
                    throw $exception;
                });
        } catch (RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame(3, $attempts);
    }

    public function testonlyRetriesForSpecifiedExceptionTypes(): void
    {
        $attempts = 0;
        $exception = new InvalidArgumentException('boom');

        try {
            Retry::times(5)
                ->withoutJitter()
                ->withDelay(0)
                ->onExceptions([RuntimeException::class])
                ->execute(function () use (&$attempts, $exception) {
                    $attempts++;
                    throw $exception;
                });
        } catch (Throwable) {
        }

        $this->assertSame(1, $attempts);
    }
}
