<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Common;

use Lalaz\Queue\Contracts\QueueLoggerInterface;

/**
 * Mock queue logger for testing.
 *
 * Records all log operations for assertions.
 *
 * @package lalaz/queue
 */
class MockQueueLogger implements QueueLoggerInterface
{
    /**
     * @var array<int, array{level: string, message: string, context: array, jobId: ?int, queue: ?string, task: ?string}>
     */
    private array $logs = [];

    /**
     * @var array<int, array{jobId: int, queue: string, task: string, executionTime: float, memoryUsage: int}>
     */
    private array $metrics = [];

    /**
     * {@inheritdoc}
     */
    public function log(
        string $level,
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void {
        $this->logs[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
            'jobId' => $jobId,
            'queue' => $queue,
            'task' => $task,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function debug(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void {
        $this->log('debug', $message, $context, $jobId, $queue, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function info(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void {
        $this->log('info', $message, $context, $jobId, $queue, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function warning(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void {
        $this->log('warning', $message, $context, $jobId, $queue, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function error(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void {
        $this->log('error', $message, $context, $jobId, $queue, $task);
    }

    /**
     * {@inheritdoc}
     */
    public function logJobMetrics(
        int $jobId,
        string $queue,
        string $task,
        float $executionTime,
        int $memoryUsage
    ): void {
        $this->metrics[] = [
            'jobId' => $jobId,
            'queue' => $queue,
            'task' => $task,
            'executionTime' => $executionTime,
            'memoryUsage' => $memoryUsage,
        ];
    }

    // =========================================================================
    // Test Helper Methods
    // =========================================================================

    /**
     * Get all logs.
     *
     * @return array<int, array>
     */
    public function getLogs(): array
    {
        return $this->logs;
    }

    /**
     * Get logs by level.
     *
     * @return array<int, array>
     */
    public function getLogsByLevel(string $level): array
    {
        return array_filter($this->logs, fn($log) => $log['level'] === $level);
    }

    /**
     * Get all metrics.
     *
     * @return array<int, array>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Check if any message was logged.
     */
    public function hasLogs(): bool
    {
        return !empty($this->logs);
    }

    /**
     * Check if any metrics were logged.
     */
    public function hasMetrics(): bool
    {
        return !empty($this->metrics);
    }

    /**
     * Get log count.
     */
    public function getLogCount(): int
    {
        return count($this->logs);
    }

    /**
     * Get the last log entry.
     */
    public function getLastLog(): ?array
    {
        return $this->logs[count($this->logs) - 1] ?? null;
    }

    /**
     * Check if a message containing text was logged at level.
     */
    public function hasLoggedMessage(string $contains, ?string $level = null): bool
    {
        foreach ($this->logs as $log) {
            if ($level !== null && $log['level'] !== $level) {
                continue;
            }
            if (str_contains($log['message'], $contains)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reset all state.
     */
    public function reset(): void
    {
        $this->logs = [];
        $this->metrics = [];
    }
}
