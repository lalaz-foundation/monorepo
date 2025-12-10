<?php declare(strict_types=1);

namespace Lalaz\Framework\Tests\Unit\Support\Resilience;

use Lalaz\Support\Resilience\CircuitBreaker;
use Lalaz\Support\Resilience\CircuitOpenException;
use Lalaz\Framework\Tests\Common\FrameworkUnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use InvalidArgumentException;
use LogicException;

#[CoversClass(CircuitBreaker::class)]
/**
 * Tests for the CircuitBreaker class.
 */
final class CircuitBreakerTest extends FrameworkUnitTestCase
{
    private CircuitBreaker $circuit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->circuit = CircuitBreaker::create()
            ->withFailureThreshold(3)
            ->withRecoveryTimeout(1)
            ->withSuccessThreshold(2);
    }

    // ============================================
    // Initial State Tests
    // ============================================

    public function teststartsInClosedState(): void
    {
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $this->circuit->getState());
        $this->assertTrue($this->circuit->isClosed());
        $this->assertFalse($this->circuit->isOpen());
        $this->assertFalse($this->circuit->isHalfOpen());
        $this->assertTrue($this->circuit->isAvailable());
    }

    public function testhasDefaultStatistics(): void
    {
        $stats = $this->circuit->getStatistics();

        $this->assertSame('closed', $stats['state']);
        $this->assertSame(0, $stats['failure_count']);
        $this->assertSame(0, $stats['success_count']);
        $this->assertSame(0, $stats['total_calls']);
        $this->assertSame(0, $stats['total_failures']);
        $this->assertSame(0.0, $stats['failure_rate']);
    }

    // ============================================
    // Successful Execution Tests
    // ============================================

    public function testexecutesCallbackAndReturnsResult(): void
    {
        $result = $this->circuit->execute(fn() => 'success');
        $this->assertSame('success', $result);
    }

    public function testcountsSuccessfulCalls(): void
    {
        $this->circuit->execute(fn() => true);
        $this->circuit->execute(fn() => true);

        $stats = $this->circuit->getStatistics();
        $this->assertSame(2, $stats['total_calls']);
        $this->assertSame(0, $stats['total_failures']);
        $this->assertSame(0, $stats['failure_count']);
    }

    public function testresetsFailureCountOnSuccess(): void
    {
        try {
            $this->circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $this->assertSame(1, $this->circuit->getStatistics()['failure_count']);

        $this->circuit->execute(fn() => true);
        $this->assertSame(0, $this->circuit->getStatistics()['failure_count']);
    }

    // ============================================
    // Failure Handling Tests
    // ============================================

    public function testcountsFailures(): void
    {
        try {
            $this->circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $stats = $this->circuit->getStatistics();
        $this->assertSame(1, $stats['failure_count']);
        $this->assertSame(1, $stats['total_failures']);
    }

    public function testrethrowsExceptionsFromCallback(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('test error');

        $this->circuit->execute(fn() => throw new RuntimeException('test error'));
    }

    public function testopensCircuitAfterReachingFailureThreshold(): void
    {
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuit->execute(fn() => throw new RuntimeException('fail'));
            } catch (RuntimeException) {}
        }

        $this->assertTrue($this->circuit->isOpen());
        $this->assertSame(CircuitBreaker::STATE_OPEN, $this->circuit->getState());
    }

    // ============================================
    // Open State Behavior Tests
    // ============================================

    public function testthrowsCircuitOpenExceptionWhenOpen(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuit->execute(fn() => throw new RuntimeException('fail'));
            } catch (RuntimeException) {}
        }

        $this->expectException(CircuitOpenException::class);
        $this->circuit->execute(fn() => true);
    }

    public function testincludesStatisticsInCircuitOpenException(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuit->execute(fn() => throw new RuntimeException('fail'));
            } catch (RuntimeException) {}
        }

        try {
            $this->circuit->execute(fn() => true);
        } catch (CircuitOpenException $e) {
            $this->assertIsArray($e->getStatistics());
            $this->assertSame(3, $e->getFailureCount());
            $this->assertGreaterThan(0, $e->getFailureRate());
        }
    }

    public function testdoesNotExecuteCallbackWhenOpen(): void
    {
        // Open the circuit
        for ($i = 0; $i < 3; $i++) {
            try {
                $this->circuit->execute(fn() => throw new RuntimeException('fail'));
            } catch (RuntimeException) {}
        }

        $called = false;
        try {
            $this->circuit->execute(function () use (&$called) {
                $called = true;
                return true;
            });
        } catch (CircuitOpenException) {}

        $this->assertFalse($called);
    }

    public function testusesFallbackWhenOpenAndFallbackIsSet(): void
    {
        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->withFallback(fn() => 'fallback value');

        try {
            $circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $result = $circuit->execute(fn() => 'primary value');
        $this->assertSame('fallback value', $result);
    }

    // ============================================
    // Half-Open State and Recovery Tests
    // ============================================

    public function testtransitionsToHalfOpenAfterTimeout(): void
    {
        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->withRecoveryTimeout(1);

        // Open the circuit
        try {
            $circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $this->assertTrue($circuit->isOpen());

        // Wait for recovery timeout
        sleep(2);

        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $circuit->getState());
        $this->assertTrue($circuit->isHalfOpen());
        $this->assertTrue($circuit->isAvailable());
    }

    public function testclosesAfterSuccessThresholdInHalfOpen(): void
    {
        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->withRecoveryTimeout(1)
            ->withSuccessThreshold(2);

        // Open the circuit
        try {
            $circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        // Wait for recovery timeout
        sleep(2);

        // Successful calls in half-open
        $circuit->execute(fn() => true);
        $this->assertTrue($circuit->isHalfOpen());

        $circuit->execute(fn() => true);
        $this->assertTrue($circuit->isClosed());
    }

    public function testreopensOnFailureInHalfOpen(): void
    {
        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->withRecoveryTimeout(1)
            ->withSuccessThreshold(5);

        // Open the circuit
        try {
            $circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        // Wait for recovery timeout
        sleep(2);

        // Verify we are now in half-open by checking state (this triggers checkRecovery)
        $this->assertSame(CircuitBreaker::STATE_HALF_OPEN, $circuit->getState());

        // Fail in half-open
        try {
            $circuit->execute(fn() => throw new RuntimeException('fail again'));
        } catch (RuntimeException) {}

        $this->assertTrue($circuit->isOpen());
    }

    // ============================================
    // Exception Filtering Tests
    // ============================================

    public function testignoresSpecifiedExceptions(): void
    {
        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->ignoreExceptions([InvalidArgumentException::class]);

        // This should NOT trip the circuit
        try {
            $circuit->execute(fn() => throw new InvalidArgumentException('ignored'));
        } catch (InvalidArgumentException) {}

        $this->assertTrue($circuit->isClosed());
        $this->assertSame(0, $circuit->getStatistics()['failure_count']);
    }

    public function testonlyTripsOnSpecifiedExceptions(): void
    {
        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->tripOn([RuntimeException::class]);

        // LogicException should NOT trip
        try {
            $circuit->execute(fn() => throw new LogicException('not tripped'));
        } catch (LogicException) {}

        $this->assertTrue($circuit->isClosed());

        // RuntimeException SHOULD trip
        try {
            $circuit->execute(fn() => throw new RuntimeException('tripped'));
        } catch (RuntimeException) {}

        $this->assertTrue($circuit->isOpen());
    }

    // ============================================
    // Manual Control Tests
    // ============================================

    public function testforceOpensTheCircuit(): void
    {
        $this->assertTrue($this->circuit->isClosed());
        $this->circuit->forceOpen();
        $this->assertTrue($this->circuit->isOpen());
    }

    public function testforceClosesTheCircuit(): void
    {
        $this->circuit->forceOpen();
        $this->assertTrue($this->circuit->isOpen());

        $this->circuit->forceClose();
        $this->assertTrue($this->circuit->isClosed());
    }

    public function testresetsAllState(): void
    {
        // Generate some state
        try {
            $this->circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $this->circuit->reset();

        $stats = $this->circuit->getStatistics();
        $this->assertSame(0, $stats['failure_count']);
        $this->assertSame(0, $stats['success_count']);
        $this->assertSame('closed', $stats['state']);
    }

    // ============================================
    // Callbacks Tests
    // ============================================

    public function testcallsOnStateChangeCallbackOnTransition(): void
    {
        $transitions = [];

        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->onStateChange(function ($from, $to) use (&$transitions) {
                $transitions[] = ['from' => $from, 'to' => $to];
            });

        try {
            $circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $this->assertCount(1, $transitions);
        $this->assertSame(CircuitBreaker::STATE_CLOSED, $transitions[0]['from']);
        $this->assertSame(CircuitBreaker::STATE_OPEN, $transitions[0]['to']);
    }

    public function testcallsOnTripCallbackWhenOpening(): void
    {
        $tripData = null;

        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->onTrip(function ($exception, $failures) use (&$tripData) {
                $tripData = [
                    'exception' => $exception,
                    'failures' => $failures,
                ];
            });

        try {
            $circuit->execute(fn() => throw new RuntimeException('trip'));
        } catch (RuntimeException) {}

        $this->assertNotNull($tripData);
        $this->assertInstanceOf(RuntimeException::class, $tripData['exception']);
        $this->assertSame(1, $tripData['failures']);
    }

    public function testcallsOnResetCallbackWhenClosing(): void
    {
        $resetCalled = false;

        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(1)
            ->onReset(function () use (&$resetCalled) {
                $resetCalled = true;
            });

        try {
            $circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $circuit->reset();

        $this->assertTrue($resetCalled);
    }

    // ============================================
    // tryExecute Tests
    // ============================================

    public function testtryExecuteReturnsNullWhenCircuitIsOpen(): void
    {
        $circuit = CircuitBreaker::create()->withFailureThreshold(1);

        try {
            $circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $result = $circuit->tryExecute(fn() => 'value');
        $this->assertNull($result);
    }

    public function testtryExecuteReturnsResultWhenCircuitIsClosed(): void
    {
        $result = $this->circuit->tryExecute(fn() => 'success');
        $this->assertSame('success', $result);
    }

    public function testtryExecuteStillThrowsCallbackExceptions(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fail');

        $this->circuit->tryExecute(fn() => throw new RuntimeException('fail'));
    }

    // ============================================
    // Statistics Tests
    // ============================================

    public function testcalculatesFailureRateCorrectly(): void
    {
        $this->circuit->execute(fn() => true);
        try {
            $this->circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}

        $stats = $this->circuit->getStatistics();
        $this->assertSame(50.0, $stats['failure_rate']);
    }

    public function testtracksOpenedAtTimestamp(): void
    {
        $circuit = CircuitBreaker::create()->withFailureThreshold(1);

        $before = time();
        try {
            $circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}
        $after = time();

        $stats = $circuit->getStatistics();
        $this->assertGreaterThanOrEqual($before, $stats['opened_at']);
        $this->assertLessThanOrEqual($after, $stats['opened_at']);
    }

    public function testtracksLastFailureAtTimestamp(): void
    {
        $before = time();
        try {
            $this->circuit->execute(fn() => throw new RuntimeException('fail'));
        } catch (RuntimeException) {}
        $after = time();

        $stats = $this->circuit->getStatistics();
        $this->assertGreaterThanOrEqual($before, $stats['last_failure_at']);
        $this->assertLessThanOrEqual($after, $stats['last_failure_at']);
    }

    // ============================================
    // Fluent Configuration Tests
    // ============================================

    public function testsupportsFluentConfiguration(): void
    {
        $circuit = CircuitBreaker::create()
            ->withFailureThreshold(10)
            ->withRecoveryTimeout(60)
            ->withSuccessThreshold(3)
            ->withFailureWindow(120)
            ->tripOn([RuntimeException::class])
            ->ignoreExceptions([InvalidArgumentException::class])
            ->withFallback(fn() => 'fallback')
            ->onStateChange(fn($from, $to) => null)
            ->onTrip(fn($e, $f) => null)
            ->onReset(fn() => null);

        $stats = $circuit->getStatistics();
        $this->assertSame(10, $stats['failure_threshold']);
        $this->assertSame(60, $stats['recovery_timeout']);
        $this->assertSame(3, $stats['success_threshold']);
    }
}
