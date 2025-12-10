<?php

declare(strict_types=1);

namespace Lalaz\Queue;

use Lalaz\Queue\Contracts\JobExecutorInterface;
use Lalaz\Queue\Contracts\JobResolverInterface;
use Lalaz\Queue\Contracts\QueueLoggerInterface;

/**
 * Default job executor implementation.
 *
 * Following SRP - this class is responsible only for executing jobs.
 * Following DIP - depends on JobResolverInterface and QueueLoggerInterface.
 */
class JobExecutor implements JobExecutorInterface
{
    private JobResolverInterface $resolver;
    private ?QueueLoggerInterface $logger;

    protected const JSON_MAX_DEPTH = 512;

    public function __construct(
        ?JobResolverInterface $resolver = null,
        ?QueueLoggerInterface $logger = null
    ) {
        $this->resolver = $resolver ?? new JobResolver();
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $job): void
    {
        $jobClass = $job['task'];
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->log('debug', 'Starting job execution', [
            'job_id' => $job['id'],
            'queue' => $job['queue'],
            'task' => $jobClass,
            'attempt' => ($job['attempts'] ?? 0) + 1,
            'max_attempts' => $job['max_attempts'] ?? 3,
        ], $job['id'], $job['queue'], $jobClass);

        if (!method_exists($jobClass, 'handle')) {
            throw new \RuntimeException(
                "Job class '{$jobClass}' must implement handle() method"
            );
        }

        $jobInstance = $this->resolver->resolve($jobClass);

        $payload = $this->decodePayload($job['payload']);

        $jobInstance->handle($payload);

        // Log metrics
        $executionTime = microtime(true) - $startTime;
        $memoryUsed = memory_get_usage(true) - $startMemory;

        $this->logMetrics($job['id'], $job['queue'], $jobClass, $executionTime, $memoryUsed);
    }

    /**
     * {@inheritdoc}
     */
    public function executeSync(string $jobClass, array $payload): bool
    {
        if (!class_exists($jobClass)) {
            $this->log('error', "Job class '{$jobClass}' does not exist");
            return false;
        }

        try {
            $jobInstance = $this->resolver->resolve($jobClass);
        } catch (\Throwable $e) {
            $this->log('error', "Failed to instantiate job '{$jobClass}': " . $e->getMessage());
            return false;
        }

        if (!method_exists($jobInstance, 'handle')) {
            $this->log('error', "Job class '{$jobClass}' must implement handle() method");
            return false;
        }

        try {
            $jobInstance->handle($payload);
            return true;
        } catch (\Throwable $e) {
            $this->log('error', "Failed to execute job '{$jobClass}' synchronously: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Decode the job payload from JSON.
     */
    private function decodePayload(string $payload): array
    {
        $decoded = json_decode($payload, true, self::JSON_MAX_DEPTH, JSON_THROW_ON_ERROR);
        return $decoded ?? [];
    }

    /**
     * Log a message if logger is available.
     */
    private function log(
        string $level,
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null
    ): void {
        if ($this->logger === null) {
            if ($level === 'error') {
                error_log($message);
            }
            return;
        }

        match ($level) {
            'debug' => $this->logger->debug($message, $context, $jobId, $queue, $task),
            'info' => $this->logger->info($message, $context, $jobId, $queue, $task),
            'warning' => $this->logger->warning($message, $context, $jobId, $queue, $task),
            'error' => $this->logger->error($message, $context, $jobId, $queue, $task),
            default => $this->logger->info($message, $context, $jobId, $queue, $task),
        };
    }

    /**
     * Log job metrics if logger is available.
     */
    private function logMetrics(
        int $jobId,
        string $queue,
        string $task,
        float $executionTime,
        int $memoryUsed
    ): void {
        if ($this->logger !== null) {
            $this->logger->logJobMetrics($jobId, $queue, $task, $executionTime, $memoryUsed);
        }
    }

    /**
     * Get the job resolver.
     */
    public function getResolver(): JobResolverInterface
    {
        return $this->resolver;
    }

    /**
     * Get the logger.
     */
    public function getLogger(): ?QueueLoggerInterface
    {
        return $this->logger;
    }
}
