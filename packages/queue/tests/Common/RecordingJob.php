<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Common;

use Lalaz\Queue\Contracts\JobInterface;

/**
 * Recording job for detailed test assertions.
 *
 * Records all execution details for comprehensive testing.
 *
 * @package lalaz/queue
 */
class RecordingJob implements JobInterface
{
    /**
     * @var array<int, array{payload: array, timestamp: float, memory: int}>
     */
    private array $executions = [];

    /**
     * {@inheritdoc}
     */
    public function handle(array $payload): void
    {
        $this->executions[] = [
            'payload' => $payload,
            'timestamp' => microtime(true),
            'memory' => memory_get_usage(true),
        ];
    }

    /**
     * Check if job was executed.
     */
    public function wasExecuted(): bool
    {
        return !empty($this->executions);
    }

    /**
     * Get execution count.
     */
    public function getExecutionCount(): int
    {
        return count($this->executions);
    }

    /**
     * Get all executions.
     *
     * @return array<int, array{payload: array, timestamp: float, memory: int}>
     */
    public function getExecutions(): array
    {
        return $this->executions;
    }

    /**
     * Get last execution.
     *
     * @return array{payload: array, timestamp: float, memory: int}|null
     */
    public function getLastExecution(): ?array
    {
        return $this->executions[count($this->executions) - 1] ?? null;
    }

    /**
     * Get last payload.
     */
    public function getLastPayload(): ?array
    {
        return $this->getLastExecution()['payload'] ?? null;
    }

    /**
     * Get all payloads.
     *
     * @return array<int, array>
     */
    public function getPayloads(): array
    {
        return array_column($this->executions, 'payload');
    }

    /**
     * Reset executions.
     */
    public function reset(): void
    {
        $this->executions = [];
    }
}
