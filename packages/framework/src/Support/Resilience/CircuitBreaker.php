<?php

declare(strict_types=1);

namespace Lalaz\Support\Resilience;

use Throwable;

/**
 * Circuit Breaker pattern implementation.
 *
 * Prevents cascading failures by stopping calls to a failing service.
 * The circuit breaker monitors failures and transitions between states:
 * - CLOSED (normal): Requests pass through normally
 * - OPEN (failing): Requests fail fast without calling the service
 * - HALF_OPEN (testing): Allows limited requests to test if service recovered
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class CircuitBreaker
{
    /**
     * Circuit is closed (healthy) - requests pass through.
     */
    public const STATE_CLOSED = 'closed';

    /**
     * Circuit is open (failing) - requests fail fast.
     */
    public const STATE_OPEN = 'open';

    /**
     * Circuit is half-open - testing if service recovered.
     */
    public const STATE_HALF_OPEN = 'half_open';

    /**
     * Current state of the circuit.
     *
     * @var string
     */
    private string $state = self::STATE_CLOSED;

    /**
     * Failure count in current window.
     *
     * @var int
     */
    private int $failureCount = 0;

    /**
     * Success count in half-open state.
     *
     * @var int
     */
    private int $successCount = 0;

    /**
     * Timestamp when the circuit was opened.
     *
     * @var int|null
     */
    private ?int $openedAt = null;

    /**
     * Last failure timestamp.
     *
     * @var int|null
     */
    private ?int $lastFailureAt = null;

    /**
     * Total calls made through the circuit.
     *
     * @var int
     */
    private int $totalCalls = 0;

    /**
     * Total failures recorded.
     *
     * @var int
     */
    private int $totalFailures = 0;

    /**
     * Number of failures before opening circuit.
     *
     * @var int
     */
    private int $failureThreshold;

    /**
     * Seconds to wait before trying half-open.
     *
     * @var int
     */
    private int $recoveryTimeout;

    /**
     * Successes required in half-open before closing.
     *
     * @var int
     */
    private int $successThreshold;

    /**
     * Seconds for failure window (sliding window).
     *
     * @var int
     */
    private int $failureWindow;

    /**
     * Exception types that should trigger the circuit.
     *
     * @var array<int, class-string<Throwable>>
     */
    private array $tripOn = [];

    /**
     * Exception types that should NOT trigger the circuit.
     *
     * @var array<int, class-string<Throwable>>
     */
    private array $ignoreExceptions = [];

    /**
     * Callback when circuit state changes.
     *
     * @var callable|null
     */
    private $onStateChange = null;

    /**
     * Callback when circuit trips open.
     *
     * @var callable|null
     */
    private $onTrip = null;

    /**
     * Callback when circuit resets to closed.
     *
     * @var callable|null
     */
    private $onReset = null;

    /**
     * Fallback when circuit is open.
     *
     * @var callable|null
     */
    private $fallback = null;

    /**
     * Create a new circuit breaker instance.
     *
     * @param int $failureThreshold Failures before opening (default: 5).
     * @param int $recoveryTimeout Seconds before trying half-open (default: 30).
     * @param int $successThreshold Successes to close from half-open (default: 2).
     * @param int $failureWindow Sliding window for failures in seconds (default: 60).
     */
    public function __construct(
        int $failureThreshold = 5,
        int $recoveryTimeout = 30,
        int $successThreshold = 2,
        int $failureWindow = 60,
    ) {
        $this->failureThreshold = max(1, $failureThreshold);
        $this->recoveryTimeout = max(1, $recoveryTimeout);
        $this->successThreshold = max(1, $successThreshold);
        $this->failureWindow = max(1, $failureWindow);
    }

    /**
     * Create a circuit breaker with default settings.
     *
     * @return self
     */
    public static function create(): self
    {
        return new self();
    }

    /**
     * Set failure threshold before opening.
     *
     * @param int $threshold Number of failures.
     * @return self
     */
    public function withFailureThreshold(int $threshold): self
    {
        $this->failureThreshold = max(1, $threshold);
        return $this;
    }

    /**
     * Set recovery timeout in seconds.
     *
     * @param int $seconds Timeout duration.
     * @return self
     */
    public function withRecoveryTimeout(int $seconds): self
    {
        $this->recoveryTimeout = max(1, $seconds);
        return $this;
    }

    /**
     * Set success threshold to close from half-open.
     *
     * @param int $threshold Number of successes.
     * @return self
     */
    public function withSuccessThreshold(int $threshold): self
    {
        $this->successThreshold = max(1, $threshold);
        return $this;
    }

    /**
     * Set failure window in seconds.
     *
     * @param int $seconds Window duration.
     * @return self
     */
    public function withFailureWindow(int $seconds): self
    {
        $this->failureWindow = max(1, $seconds);
        return $this;
    }

    /**
     * Set exception types that trip the circuit.
     *
     * @param array<int, class-string<Throwable>> $exceptions Exception class names.
     * @return self
     */
    public function tripOn(array $exceptions): self
    {
        $this->tripOn = $exceptions;
        return $this;
    }

    /**
     * Set exception types to ignore (don't count as failures).
     *
     * @param array<int, class-string<Throwable>> $exceptions Exception class names.
     * @return self
     */
    public function ignoreExceptions(array $exceptions): self
    {
        $this->ignoreExceptions = $exceptions;
        return $this;
    }

    /**
     * Set callback for state changes.
     *
     * @param callable(string $from, string $to): void $callback State change callback.
     * @return self
     */
    public function onStateChange(callable $callback): self
    {
        $this->onStateChange = $callback;
        return $this;
    }

    /**
     * Set callback when circuit trips open.
     *
     * @param callable(Throwable $exception, int $failures): void $callback Trip callback.
     * @return self
     */
    public function onTrip(callable $callback): self
    {
        $this->onTrip = $callback;
        return $this;
    }

    /**
     * Set callback when circuit resets to closed.
     *
     * @param callable(): void $callback Reset callback.
     * @return self
     */
    public function onReset(callable $callback): self
    {
        $this->onReset = $callback;
        return $this;
    }

    /**
     * Set fallback when circuit is open.
     *
     * @param callable(): mixed $fallback Fallback callable.
     * @return self
     */
    public function withFallback(callable $fallback): self
    {
        $this->fallback = $fallback;
        return $this;
    }

    /**
     * Execute a callback through the circuit breaker.
     *
     * @template T
     * @param callable(): T $callback The operation to execute.
     * @return T The callback result.
     * @throws CircuitOpenException When circuit is open and no fallback defined.
     * @throws Throwable When the callback throws an exception.
     */
    public function execute(callable $callback): mixed
    {
        $this->totalCalls++;

        // Check if we should transition from open to half-open
        $this->checkRecovery();

        // If circuit is open, fail fast or use fallback
        if ($this->state === self::STATE_OPEN) {
            if ($this->fallback !== null) {
                return ($this->fallback)();
            }

            throw new CircuitOpenException(
                'Circuit breaker is open. Service is unavailable.',
                $this->getStatistics(),
            );
        }

        try {
            $result = $callback();
            $this->recordSuccess();
            return $result;
        } catch (Throwable $e) {
            if ($this->shouldTripOn($e)) {
                $this->recordFailure($e);
            }
            throw $e;
        }
    }

    /**
     * Execute without throwing CircuitOpenException (returns null if open).
     *
     * @template T
     * @param callable(): T $callback The operation to execute.
     * @return T|null The callback result or null if circuit is open.
     */
    public function tryExecute(callable $callback): mixed
    {
        try {
            return $this->execute($callback);
        } catch (CircuitOpenException) {
            return null;
        }
    }

    /**
     * Get current state.
     *
     * @return string One of STATE_CLOSED, STATE_OPEN, or STATE_HALF_OPEN.
     */
    public function getState(): string
    {
        $this->checkRecovery();
        return $this->state;
    }

    /**
     * Check if circuit is allowing requests.
     *
     * @return bool True if circuit is closed or half-open.
     */
    public function isAvailable(): bool
    {
        $this->checkRecovery();
        return $this->state !== self::STATE_OPEN;
    }

    /**
     * Check if circuit is open (failing).
     *
     * @return bool True if circuit is open.
     */
    public function isOpen(): bool
    {
        $this->checkRecovery();
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Check if circuit is closed (healthy).
     *
     * @return bool True if circuit is closed.
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Check if circuit is half-open (testing).
     *
     * @return bool True if circuit is half-open.
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    /**
     * Force circuit to open state.
     *
     * @return void
     */
    public function forceOpen(): void
    {
        $this->transitionTo(self::STATE_OPEN);
        $this->openedAt = time();
    }

    /**
     * Force circuit to closed state.
     *
     * @return void
     */
    public function forceClose(): void
    {
        $this->reset();
    }

    /**
     * Reset circuit to initial state.
     *
     * @return void
     */
    public function reset(): void
    {
        $previousState = $this->state;
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->openedAt = null;
        $this->lastFailureAt = null;

        if ($previousState !== self::STATE_CLOSED && $this->onReset !== null) {
            ($this->onReset)();
        }

        if ($previousState !== self::STATE_CLOSED && $this->onStateChange !== null) {
            ($this->onStateChange)($previousState, self::STATE_CLOSED);
        }
    }

    /**
     * Get circuit statistics.
     *
     * @return array<string, mixed> Statistics array with state, counts, and timestamps.
     */
    public function getStatistics(): array
    {
        return [
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'success_count' => $this->successCount,
            'total_calls' => $this->totalCalls,
            'total_failures' => $this->totalFailures,
            'failure_rate' => $this->totalCalls > 0
                ? round(($this->totalFailures / $this->totalCalls) * 100, 2)
                : 0.0,
            'opened_at' => $this->openedAt,
            'last_failure_at' => $this->lastFailureAt,
            'failure_threshold' => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
            'success_threshold' => $this->successThreshold,
        ];
    }

    /**
     * Record a successful call.
     *
     * @return void
     */
    private function recordSuccess(): void
    {
        if ($this->state === self::STATE_HALF_OPEN) {
            $this->successCount++;

            if ($this->successCount >= $this->successThreshold) {
                $this->reset();
            }
        } else {
            // Reset failure count on success in closed state
            $this->failureCount = 0;
        }
    }

    /**
     * Record a failed call.
     *
     * @param Throwable $exception The exception that caused the failure.
     * @return void
     */
    private function recordFailure(Throwable $exception): void
    {
        $this->totalFailures++;
        $this->lastFailureAt = time();

        if ($this->state === self::STATE_HALF_OPEN) {
            // Any failure in half-open goes back to open
            $this->transitionTo(self::STATE_OPEN);
            $this->openedAt = time();
            return;
        }

        // In closed state, check if we've exceeded threshold
        $this->failureCount++;

        // Check if failures are within the window
        if ($this->isWithinFailureWindow()) {
            if ($this->failureCount >= $this->failureThreshold) {
                $this->transitionTo(self::STATE_OPEN);
                $this->openedAt = time();

                if ($this->onTrip !== null) {
                    ($this->onTrip)($exception, $this->failureCount);
                }
            }
        } else {
            // Reset failure count if outside window
            $this->failureCount = 1;
            $this->lastFailureAt = time();
        }
    }

    /**
     * Check if we should transition from open to half-open.
     *
     * @return void
     */
    private function checkRecovery(): void
    {
        if ($this->state !== self::STATE_OPEN) {
            return;
        }

        if ($this->openedAt === null) {
            return;
        }

        $elapsed = time() - $this->openedAt;

        if ($elapsed >= $this->recoveryTimeout) {
            $this->transitionTo(self::STATE_HALF_OPEN);
            $this->successCount = 0;
        }
    }

    /**
     * Check if failure is within the failure window.
     *
     * @return bool True if within window.
     */
    private function isWithinFailureWindow(): bool
    {
        if ($this->lastFailureAt === null) {
            return true;
        }

        $elapsed = time() - $this->lastFailureAt;
        return $elapsed <= $this->failureWindow;
    }

    /**
     * Determine if exception should trip the circuit.
     *
     * @param Throwable $exception The exception to check.
     * @return bool True if this exception should count as a failure.
     */
    private function shouldTripOn(Throwable $exception): bool
    {
        // Check if explicitly ignored
        foreach ($this->ignoreExceptions as $ignored) {
            if (is_a($exception, $ignored)) {
                return false;
            }
        }

        // If no specific exceptions configured, trip on all
        if (empty($this->tripOn)) {
            return true;
        }

        // Check if it matches any configured exceptions
        foreach ($this->tripOn as $tripException) {
            if (is_a($exception, $tripException)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Transition to a new state.
     *
     * @param string $newState The new state to transition to.
     * @return void
     */
    private function transitionTo(string $newState): void
    {
        $previousState = $this->state;
        $this->state = $newState;

        if ($this->onStateChange !== null && $previousState !== $newState) {
            ($this->onStateChange)($previousState, $newState);
        }
    }
}
