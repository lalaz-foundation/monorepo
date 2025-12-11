<?php

declare(strict_types=1);

namespace Lalaz\Support\Resilience;

use Lalaz\Exceptions\FrameworkException;

/**
 * Exception thrown when a circuit breaker is open and blocking requests.
 *
 * This exception is thrown by the CircuitBreaker when it is in the OPEN state
 * and a call is attempted. It contains statistics about the circuit breaker
 * state at the time the exception was thrown.
 *
 * Example handling:
 * ```php
 * try {
 *     $circuit->call(fn() => $service->request());
 * } catch (CircuitOpenException $e) {
 *     // Get circuit statistics
 *     $stats = $e->getStatistics();
 *     $failures = $e->getFailureCount();
 *     $openedAt = $e->getOpenedAt();
 *
 *     // Return cached response or fallback
 *     return $cache->get('fallback_response');
 * }
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class CircuitOpenException extends FrameworkException
{
    /**
     * Create a new circuit open exception.
     *
     * @param string $message The exception message
     * @param array<string, mixed> $statistics Circuit breaker statistics at time of exception
     */
    public function __construct(string $message, array $statistics = [])
    {
        parent::__construct($message, $statistics);
    }

    /**
     * Get the circuit breaker statistics at time of exception.
     *
     * Returns an array containing:
     * - state: Current circuit state (OPEN)
     * - failure_count: Number of consecutive failures
     * - success_count: Number of consecutive successes
     * - opened_at: Timestamp when circuit opened
     * - failure_rate: Current failure rate percentage
     * - circuit_name: Name of the circuit (if provided)
     *
     * @return array<string, mixed> Circuit breaker statistics
     */
    public function getStatistics(): array
    {
        return $this->getContext();
    }

    /**
     * Get the failure count when the circuit opened.
     *
     * @return int The number of consecutive failures
     */
    public function getFailureCount(): int
    {
        return $this->getContext()['failure_count'] ?? 0;
    }

    /**
     * Get the timestamp when the circuit opened.
     *
     * @return int|null Unix timestamp when circuit opened, or null if not set
     */
    public function getOpenedAt(): ?int
    {
        return $this->getContext()['opened_at'] ?? null;
    }

    /**
     * Get the failure rate at time of exception.
     *
     * @return float The failure rate as a percentage (0.0 to 100.0)
     */
    public function getFailureRate(): float
    {
        return $this->getContext()['failure_rate'] ?? 0.0;
    }

    /**
     * Create exception with circuit name.
     *
     * Factory method for creating a CircuitOpenException with a named circuit.
     * The circuit name is included in the statistics for easier debugging.
     *
     * @param string $circuitName The name of the circuit breaker
     * @param array<string, mixed> $statistics Circuit breaker statistics
     * @return self The exception instance
     */
    public static function forCircuit(string $circuitName, array $statistics = []): self
    {
        $statistics['circuit_name'] = $circuitName;

        return new self(
            "Circuit '{$circuitName}' is open and blocking requests.",
            $statistics,
        );
    }
}
