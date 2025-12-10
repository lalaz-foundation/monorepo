<?php

declare(strict_types=1);

namespace Lalaz\Queue\Drivers;

use Lalaz\Queue\Contracts\JobInterface;
use Lalaz\Queue\Contracts\QueueDriverInterface;
use Lalaz\Support\Errors;

class InMemoryQueueDriver implements QueueDriverInterface
{
    protected array $queue = [];

    public function add(
        string $jobClass,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        ?int $delay = null,
        array $options = [],
    ): bool {
        $availableAt = $delay ? time() + $delay : time();

        $this->queue[] = [
            'task' => $jobClass,
            'payload' => $payload,
            'queue' => $queue,
            'priority' => $priority,
            'options' => $options,
            'available_at' => $availableAt,
            'status' => $delay ? 'delayed' : 'pending',
        ];

        return true;
    }

    public function process(?string $queue = null): void
    {
        $now = time();

        foreach ($this->queue as $key => &$job) {
            if ($job['status'] === 'delayed' && $job['available_at'] <= $now) {
                $job['status'] = 'pending';
            }

            if ($job['status'] !== 'pending') {
                continue;
            }

            // Filter by queue if specified
            if ($queue !== null && $job['queue'] !== $queue) {
                continue;
            }

            $job['status'] = 'processing';

            try {
                $jobClass = $job['task'];

                if (!class_exists($jobClass)) {
                    Errors::throwRuntimeError(
                        "Job class '{$jobClass}' does not exist",
                        ['job' => $jobClass],
                    );
                }

                if (!method_exists($jobClass, 'handle')) {
                    Errors::throwRuntimeError(
                        "Job class '{$jobClass}' must implement handle() method",
                        ['job' => $jobClass],
                    );
                }

                $instance = resolve($jobClass);

                if (!($instance instanceof JobInterface)) {
                    Errors::throwRuntimeError(
                        "Job class '{$jobClass}' must implement JobInterface",
                        ['job' => $jobClass],
                    );
                }

                $instance->handle($job['payload']);

                $job['status'] = 'completed';
            } catch (\Throwable $e) {
                $job['status'] = 'failed';
                error_log(
                    "Failed to process job {$job['task']}: " . $e->getMessage(),
                );
            }

            break;
        }

        unset($job);
    }

    public function cleanup(): int
    {
        $removed = 0;
        $originalCount = count($this->queue);

        // Filter out completed and failed jobs
        $this->queue = array_filter($this->queue, function ($job) use (
            &$removed,
        ) {
            if (in_array($job['status'], ['completed', 'failed'], true)) {
                $removed++;
                return false;
            }
            return true;
        });

        // Reindex array to maintain sequential keys
        $this->queue = array_values($this->queue);

        return $removed;
    }

    public function all(): array
    {
        return $this->queue;
    }

    public function processBatch(
        int $batchSize = 10,
        ?string $queue = null,
        int $maxExecutionTime = 55,
    ): array {
        $startTime = time();
        $processed = 0;
        $successful = 0;
        $failed = 0;

        while ($processed < $batchSize) {
            if (time() - $startTime >= $maxExecutionTime) {
                break;
            }

            $initialCount = count($this->queue);
            $this->process($queue);

            // Check if a job was processed
            if (count($this->queue) === $initialCount) {
                break; // No jobs available
            }

            $processed++;

            // Check last job status
            foreach ($this->queue as $job) {
                if ($job['status'] === 'completed') {
                    $successful++;
                    break;
                } elseif ($job['status'] === 'failed') {
                    $failed++;
                    break;
                }
            }
        }

        return [
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed,
            'execution_time' => time() - $startTime,
        ];
    }

    public function getStats(?string $queue = null): array
    {
        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'delayed' => 0,
        ];

        foreach ($this->queue as $job) {
            if ($queue !== null && $job['queue'] !== $queue) {
                continue;
            }

            if (isset($stats[$job['status']])) {
                $stats[$job['status']]++;
            }
        }

        return $stats;
    }

    public function getFailedJobs(int $limit = 50, int $offset = 0): array
    {
        $failedJobs = array_filter($this->queue, function ($job) {
            return $job['status'] === 'failed';
        });

        return array_slice(array_values($failedJobs), $offset, $limit);
    }

    public function getFailedJob(int $id): ?array
    {
        if (
            isset($this->queue[$id]) &&
            $this->queue[$id]['status'] === 'failed'
        ) {
            return $this->queue[$id];
        }

        return null;
    }

    public function retryFailedJob(int $id): bool
    {
        if (
            !isset($this->queue[$id]) ||
            $this->queue[$id]['status'] !== 'failed'
        ) {
            return false;
        }

        $this->queue[$id]['status'] = 'pending';
        return true;
    }

    public function retryAllFailedJobs(?string $queue = null): int
    {
        $retried = 0;

        foreach ($this->queue as &$job) {
            if ($job['status'] !== 'failed') {
                continue;
            }

            if ($queue !== null && $job['queue'] !== $queue) {
                continue;
            }

            $job['status'] = 'pending';
            $retried++;
        }

        unset($job);
        return $retried;
    }

    public function purgeOldJobs(int $olderThanDays = 7): int
    {
        // InMemory driver doesn't store timestamps for completion
        // This is a no-op for this driver, but required by interface
        return 0;
    }

    public function purgeFailedJobs(?string $queue = null): int
    {
        $purged = 0;
        $originalCount = count($this->queue);

        $this->queue = array_filter($this->queue, function ($job) use (
            $queue,
            &$purged,
        ) {
            if ($job['status'] !== 'failed') {
                return true;
            }

            if ($queue !== null && $job['queue'] !== $queue) {
                return true;
            }

            $purged++;
            return false;
        });

        $this->queue = array_values($this->queue);
        return $purged;
    }
}
