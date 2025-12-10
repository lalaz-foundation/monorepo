<?php

declare(strict_types=1);

namespace Lalaz\Queue;

use Lalaz\Config\Config;
use Lalaz\Queue\Contracts\JobExecutorInterface;
use Lalaz\Queue\Contracts\JobInterface;
use Lalaz\Queue\Contracts\QueueDriverInterface;
use Lalaz\Queue\Contracts\QueueManagerInterface;

/**
 * Coordinates job enqueueing and processing using a pluggable queue driver.
 * When queueing is disabled via config, jobs run synchronously for DX.
 *
 * Following DIP - depends on interfaces (QueueDriverInterface, JobExecutorInterface).
 * Following ISP - implements QueueManagerInterface which extends segregated interfaces.
 */
class QueueManager implements QueueManagerInterface
{
    protected QueueDriverInterface $driver;

    /**
     * Job executor for synchronous execution (DIP).
     */
    protected ?JobExecutorInterface $executor;

    public static function isEnabled(): bool
    {
        $enabled = Config::getBool('queue.enabled') ?? false;
        return filter_var($enabled, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Create a new QueueManager instance.
     *
     * @param QueueDriverInterface $driver The queue driver (injected via DI)
     * @param JobExecutorInterface|null $executor Optional executor for sync jobs
     */
    public function __construct(
        QueueDriverInterface $driver,
        ?JobExecutorInterface $executor = null
    ) {
        $this->driver = $driver;
        $this->executor = $executor;
    }

    /**
     * {@inheritdoc}
     */
    public function add(
        string $jobClass,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        ?int $delay = null,
        array $options = [],
    ): bool {
        return $this->addJob($jobClass, $payload, $queue, $priority, $delay, $options);
    }

    /**
     * Enqueue a job or run synchronously when queueing is disabled.
     *
     * @param class-string $jobClass Class name implementing JobInterface.
     * @param array $payload Arbitrary payload passed to handle().
     * @param string $queue Queue name.
     * @param int $priority Lower number = higher priority (driver-specific).
     * @param int|null $delay Delay in seconds before the job becomes available.
     * @param array $options Driver-specific options.
     *
     * @return bool True when enqueued or executed successfully.
     */
    public function addJob(
        string $jobClass,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        ?int $delay = null,
        array $options = [],
    ): bool {
        // If queue is disabled, execute job synchronously
        if (!self::isEnabled()) {
            return $this->executeSynchronously($jobClass, $payload);
        }

        return $this->driver->add(
            $jobClass,
            $payload,
            $queue,
            $priority,
            $delay,
            $options,
        );
    }

    /**
     * Execute a job synchronously.
     */
    protected function executeSynchronously(string $jobClass, array $payload): bool
    {
        // Use injected executor if available (DIP)
        if ($this->executor !== null) {
            return $this->executor->executeSync($jobClass, $payload);
        }

        // Fallback to internal execution
        if (!class_exists($jobClass)) {
            error_log("Job class '{$jobClass}' does not exist");
            return false;
        }

        try {
            $jobInstance = resolve($jobClass);
        } catch (\Throwable $e) {
            error_log(
                "Failed to instantiate job '{$jobClass}': " .
                    $e->getMessage(),
            );
            return false;
        }

        // Validate that job implements JobInterface
        if (!($jobInstance instanceof JobInterface)) {
            error_log(
                "Job class '{$jobClass}' must implement JobInterface",
            );
            return false;
        }

        if (!method_exists($jobInstance, 'handle')) {
            error_log(
                "Job class '{$jobClass}' must implement handle() method",
            );
            return false;
        }

        try {
            $jobInstance->handle($payload);
            return true;
        } catch (\Throwable $e) {
            error_log(
                'Failed to execute job synchronously: ' . $e->getMessage(),
            );
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function process(?string $queue = null): void
    {
        $this->driver->process($queue);
    }

    public function processJobs(?string $queue = null): void
    {
        $this->process($queue);
    }

    /**
     * {@inheritdoc}
     */
    public function processBatch(
        int $batchSize = 10,
        ?string $queue = null,
        int $maxExecutionTime = 55,
    ): array {
        return $this->driver->processBatch(
            $batchSize,
            $queue,
            $maxExecutionTime,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getStats(?string $queue = null): array
    {
        return $this->driver->getStats($queue);
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedJobs(int $limit = 50, int $offset = 0): array
    {
        return $this->driver->getFailedJobs($limit, $offset);
    }

    /**
     * {@inheritdoc}
     */
    public function getFailedJob(int $id): ?array
    {
        return $this->driver->getFailedJob($id);
    }

    /**
     * {@inheritdoc}
     */
    public function retryFailedJob(int $id): bool
    {
        return $this->driver->retryFailedJob($id);
    }

    /**
     * {@inheritdoc}
     */
    public function retryAllFailedJobs(?string $queue = null): int
    {
        return $this->driver->retryAllFailedJobs($queue);
    }

    /**
     * {@inheritdoc}
     */
    public function purgeOldJobs(int $olderThanDays = 7): int
    {
        return $this->driver->purgeOldJobs($olderThanDays);
    }

    /**
     * {@inheritdoc}
     */
    public function purgeFailedJobs(?string $queue = null): int
    {
        return $this->driver->purgeFailedJobs($queue);
    }

    /**
     * Get the queue driver.
     */
    public function getDriver(): QueueDriverInterface
    {
        return $this->driver;
    }

    /**
     * Get the job executor.
     */
    public function getExecutor(): ?JobExecutorInterface
    {
        return $this->executor;
    }
}
