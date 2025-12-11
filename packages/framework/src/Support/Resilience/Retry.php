<?php

declare(strict_types=1);

namespace Lalaz\Support\Resilience;

use Throwable;

/**
 * Retry pattern implementation with exponential backoff.
 *
 * Provides a fluent API for retrying operations that may fail temporarily.
 * Supports configurable delay, exponential backoff, jitter, and selective
 * exception handling.
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
final class Retry
{
    /**
     * Default number of retry attempts.
     */
    private const DEFAULT_ATTEMPTS = 3;

    /**
     * Default delay between retries in milliseconds.
     */
    private const DEFAULT_DELAY_MS = 100;

    /**
     * Default multiplier for exponential backoff.
     */
    private const DEFAULT_MULTIPLIER = 2.0;

    /**
     * Maximum delay cap in milliseconds.
     */
    private const MAX_DELAY_MS = 10000;

    /**
     * Number of retry attempts.
     *
     * @var int
     */
    private int $attempts;

    /**
     * Base delay between retries in milliseconds.
     *
     * @var int
     */
    private int $delayMs;

    /**
     * Multiplier for exponential backoff.
     *
     * @var float
     */
    private float $multiplier;

    /**
     * Whether to add random jitter to delays.
     *
     * @var bool
     */
    private bool $useJitter;

    /**
     * Exception types to retry on.
     *
     * @var array<int, class-string<Throwable>>
     */
    private array $retryOn;

    /**
     * Callback invoked on each retry.
     *
     * @var callable|null
     */
    private $onRetry;

    /**
     * Callback invoked when all retries fail.
     *
     * @var callable|null
     */
    private $onFailure;

    /**
     * Create a new Retry instance.
     *
     * @param int $attempts Number of retry attempts.
     * @param int $delayMs Base delay in milliseconds.
     * @param float $multiplier Backoff multiplier.
     * @param bool $useJitter Whether to use jitter.
     * @param array<int, class-string<Throwable>> $retryOn Exception types to retry on.
     * @param callable|null $onRetry Callback for each retry.
     * @param callable|null $onFailure Callback for final failure.
     */
    private function __construct(
        int $attempts = self::DEFAULT_ATTEMPTS,
        int $delayMs = self::DEFAULT_DELAY_MS,
        float $multiplier = self::DEFAULT_MULTIPLIER,
        bool $useJitter = true,
        array $retryOn = [],
        ?callable $onRetry = null,
        ?callable $onFailure = null,
    ) {
        $this->attempts = max(1, $attempts);
        $this->delayMs = max(0, $delayMs);
        $this->multiplier = max(1.0, $multiplier);
        $this->useJitter = $useJitter;
        $this->retryOn = $retryOn;
        $this->onRetry = $onRetry;
        $this->onFailure = $onFailure;
    }

    /**
     * Create a Retry instance with the specified number of attempts.
     *
     * @param int $attempts Number of retry attempts.
     * @return self
     */
    public static function times(int $attempts = self::DEFAULT_ATTEMPTS): self
    {
        return new self($attempts);
    }

    /**
     * Set the base delay between retries.
     *
     * @param int $delayMs Delay in milliseconds.
     * @return self
     */
    public function withDelay(int $delayMs): self
    {
        $this->delayMs = max(0, $delayMs);
        return $this;
    }

    /**
     * Set the exponential backoff multiplier.
     *
     * @param float $multiplier Multiplier value (minimum 1.0).
     * @return self
     */
    public function withBackoff(float $multiplier): self
    {
        $this->multiplier = max(1.0, $multiplier);
        return $this;
    }

    /**
     * Disable random jitter on delays.
     *
     * @return self
     */
    public function withoutJitter(): self
    {
        $this->useJitter = false;
        return $this;
    }

    /**
     * Set specific exception types to retry on.
     *
     * @param array<int, class-string<Throwable>> $exceptions Exception class names.
     * @return self
     */
    public function onExceptions(array $exceptions): self
    {
        $this->retryOn = $exceptions;
        return $this;
    }

    /**
     * Set callback to invoke on each retry attempt.
     *
     * @param callable(int $attempt, Throwable $e, int $delay): void $callback
     * @return self
     */
    public function onRetry(callable $callback): self
    {
        $this->onRetry = $callback;
        return $this;
    }

    /**
     * Set callback to invoke when all retries fail.
     *
     * @param callable(Throwable $e, int $attempts): void $callback
     * @return self
     */
    public function onFailure(callable $callback): self
    {
        $this->onFailure = $callback;
        return $this;
    }

    /**
     * Execute a callback with retry logic.
     *
     * @template T
     * @param callable(): T $callback The operation to execute.
     * @return T The callback result.
     * @throws Throwable When all retries are exhausted.
     */
    public function execute(callable $callback): mixed
    {
        $attempt = 0;
        $currentDelay = $this->delayMs;
        $lastException = null;

        while ($attempt < $this->attempts) {
            try {
                return $callback();
            } catch (Throwable $e) {
                $lastException = $e;
                $attempt++;

                if (!$this->shouldRetry($e) || $attempt >= $this->attempts) {
                    break;
                }

                $delay = $this->calculateDelay($currentDelay);

                if ($this->onRetry !== null) {
                    ($this->onRetry)($attempt, $e, $delay);
                }

                if ($delay > 0) {
                    usleep($delay * 1000);
                }

                $currentDelay = (int) min(
                    $currentDelay * $this->multiplier,
                    self::MAX_DELAY_MS,
                );
            }
        }

        if ($this->onFailure !== null && $lastException !== null) {
            ($this->onFailure)($lastException, $this->attempts);
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        return null;
    }

    /**
     * Execute without throwing (returns null on failure).
     *
     * @template T
     * @param callable(): T $callback The operation to execute.
     * @return T|null The callback result or null if all retries fail.
     */
    public function tryExecute(callable $callback): mixed
    {
        try {
            return $this->execute($callback);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Determine if the operation should be retried for this exception.
     *
     * @param Throwable $exception The exception to check.
     * @return bool True if should retry.
     */
    private function shouldRetry(Throwable $exception): bool
    {
        if ($this->retryOn === []) {
            return true;
        }

        foreach ($this->retryOn as $class) {
            if (is_a($exception, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calculate the delay with optional jitter.
     *
     * @param int $baseDelay The base delay in milliseconds.
     * @return int The calculated delay with jitter applied.
     */
    private function calculateDelay(int $baseDelay): int
    {
        if (!$this->useJitter || $baseDelay === 0) {
            return $baseDelay;
        }

        $min = (int) ($baseDelay * 0.5);
        $max = $baseDelay;

        return random_int($min, $max);
    }
}
