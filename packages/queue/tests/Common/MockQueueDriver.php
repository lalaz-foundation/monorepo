<?php declare(strict_types=1);

namespace Lalaz\Queue\Tests\Common;

use Lalaz\Queue\Contracts\QueueDriverInterface;

/**
 * Mock queue driver for testing.
 *
 * Records all operations for assertions without actual queue functionality.
 *
 * @package lalaz/queue
 */
class MockQueueDriver implements QueueDriverInterface
{
    /**
     * @var array<int, array{jobClass: string, payload: array, queue: string, priority: int, delay: ?int, options: array}>
     */
    public array $addedJobs = [];

    /**
     * @var int
     */
    public int $processCallCount = 0;

    /**
     * @var string|null
     */
    public ?string $lastProcessedQueue = null;

    /**
     * @var array<int, array>
     */
    public array $failedJobs = [];

    /**
     * @var bool
     */
    public bool $addShouldSucceed = true;

    /**
     * @var array{pending: int, processing: int, completed: int, failed: int}
     */
    public array $stats = [
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
    ];

    /**
     * {@inheritdoc}
     */
    public function add(
        string $jobClass,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        ?int $delay = null,
        array $options = []
    ): bool {
        if (!$this->addShouldSucceed) {
            return false;
        }

        $this->addedJobs[] = [
            'jobClass' => $jobClass,
            'payload' => $payload,
            'queue' => $queue,
            'priority' => $priority,
            'delay' => $delay,
            'options' => $options,
        ];

        $this->stats['pending']++;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function process(?string $queue = null): void
    {
        $this->processCallCount++;
        $this->lastProcessedQueue = $queue;
    }

    /**
     * {@inheritdoc}
     */
    public function processBatch(int $batchSize = 10, ?string $queue = null, int $maxExecutionTime = 55): array
    {
        return [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'execution_time' => 0,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(?string $queue = null): array
    {
        if ($queue !== null) {
            $pending = 0;
            foreach ($this->addedJobs as $job) {
                if ($job['queue'] === $queue) {
                    $pending++;
                }
            }
            return array_merge($this->stats, ['pending' => $pending]);
        }

        return array_merge($this->stats, ['pending' => count($this->addedJobs)]);
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedJobs(int $limit = 50, int $offset = 0): array
    {
        return array_slice($this->failedJobs, $offset, $limit);
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedJob(int $id): ?array
    {
        foreach ($this->failedJobs as $job) {
            if ($job['id'] === $id) {
                return $job;
            }
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function retryFailedJob(int $id): bool
    {
        foreach ($this->failedJobs as $key => $job) {
            if ($job['id'] === $id) {
                unset($this->failedJobs[$key]);
                $this->failedJobs = array_values($this->failedJobs);
                $this->stats['failed']--;
                $this->stats['pending']++;
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function retryAllFailedJobs(?string $queue = null): int
    {
        $retried = 0;
        foreach ($this->failedJobs as $key => $job) {
            if ($queue === null || $job['queue'] === $queue) {
                unset($this->failedJobs[$key]);
                $retried++;
            }
        }
        $this->failedJobs = array_values($this->failedJobs);
        $this->stats['failed'] -= $retried;
        $this->stats['pending'] += $retried;
        return $retried;
    }

    /**
     * {@inheritdoc}
     */
    public function purgeOldJobs(int $olderThanDays = 7): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function purgeFailedJobs(?string $queue = null): int
    {
        $count = count($this->failedJobs);
        if ($queue !== null) {
            $count = 0;
            foreach ($this->failedJobs as $key => $job) {
                if ($job['queue'] === $queue) {
                    unset($this->failedJobs[$key]);
                    $count++;
                }
            }
            $this->failedJobs = array_values($this->failedJobs);
        } else {
            $this->failedJobs = [];
        }
        $this->stats['failed'] -= $count;
        return $count;
    }

    // =========================================================================
    // Test Helper Methods
    // =========================================================================

    /**
     * Add a failed job for testing.
     */
    public function addFailedJob(array $job): void
    {
        $this->failedJobs[] = array_merge([
            'id' => count($this->failedJobs) + 1,
            'queue' => 'default',
            'task' => FakeJob::class,
            'payload' => '{}',
            'last_error' => 'Test error',
            'attempts' => 3,
            'max_attempts' => 3,
        ], $job);
        $this->stats['failed']++;
    }

    /**
     * Reset all state.
     */
    public function reset(): void
    {
        $this->addedJobs = [];
        $this->failedJobs = [];
        $this->processCallCount = 0;
        $this->lastProcessedQueue = null;
        $this->addShouldSucceed = true;
        $this->stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
    }

    /**
     * Get job by index.
     */
    public function getJob(int $index): ?array
    {
        return $this->addedJobs[$index] ?? null;
    }

    /**
     * Get jobs by queue.
     *
     * @return array<int, array>
     */
    public function getJobsByQueue(string $queue): array
    {
        return array_filter($this->addedJobs, fn($job) => $job['queue'] === $queue);
    }
}
