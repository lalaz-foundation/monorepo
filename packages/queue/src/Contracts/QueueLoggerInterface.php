<?php

declare(strict_types=1);

namespace Lalaz\Queue\Contracts;

/**
 * Interface for queue logging operations.
 *
 * Following DIP - queue components depend on this abstraction
 * instead of directly using QueueLogger.
 */
interface QueueLoggerInterface
{
    /**
     * Log a debug message.
     */
    public function debug(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void;

    /**
     * Log an info message.
     */
    public function info(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void;

    /**
     * Log a warning message.
     */
    public function warning(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void;

    /**
     * Log an error message.
     */
    public function error(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void;

    /**
     * Log job execution metrics.
     */
    public function logJobMetrics(
        int $jobId,
        string $queue,
        string $task,
        float $executionTime,
        int $memoryUsed
    ): void;
}
