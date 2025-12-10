<?php

declare(strict_types=1);

namespace Lalaz\Queue\Drivers;

use Lalaz\Config\Config;
use Lalaz\Database\Connection;
use Lalaz\Queue\Contracts\JobExecutorInterface;
use Lalaz\Queue\Contracts\QueueDriverInterface;
use Lalaz\Queue\Contracts\QueueLoggerInterface;
use Lalaz\Queue\JobExecutor;
use Lalaz\Queue\JobResolver;
use Lalaz\Queue\Migrations\QueueMigration;
use Lalaz\Queue\QueueLogger;
use Lalaz\Queue\RetryStrategy;
use Lalaz\Support\Errors;

/**
 * Abstract database queue driver with injected dependencies.
 *
 * Following DIP - depends on JobExecutorInterface and QueueLoggerInterface.
 * Following SRP - delegates job execution to JobExecutor.
 */
abstract class AbstractDatabaseQueueDriver implements QueueDriverInterface
{
    protected Connection $db;

    protected string $tableName;

    protected int $jobTimeout;

    protected QueueLoggerInterface $logger;

    protected JobExecutorInterface $executor;

    protected const JSON_MAX_DEPTH = 512;

    public function __construct(
        Connection $db,
        ?JobExecutorInterface $executor = null,
        ?QueueLoggerInterface $logger = null
    ) {
        $this->db = $db;
        $this->tableName = $this->sanitizeTableName(
            Config::get('queue.database.table') ?: 'jobs',
        );
        $this->jobTimeout = (int) (Config::get('queue.job_timeout') ?: 300); // 5 minutes default

        // Use injected logger or create default
        $this->logger = $logger ?? new QueueLogger($db);

        // Use injected executor or create default
        $this->executor = $executor ?? new JobExecutor(
            new JobResolver(),
            $this->logger
        );

        $this->ensureJobsTableExists();
    }

    protected function sanitizeTableName(string $tableName): string
    {
        // Only allow alphanumeric and underscores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
            Errors::throwInvalidArgument(
                "Invalid table name: '{$tableName}'. Only alphanumeric characters and underscores are allowed.",
                ['table' => $tableName],
            );
        }

        return $tableName;
    }

    public function add(
        string $jobClass,
        array $payload = [],
        string $queue = 'default',
        int $priority = 5,
        ?int $delay = null,
        array $options = [],
    ): bool {
        try {
            $now = date('Y-m-d H:i:s');
            $availableAt = $delay ? date('Y-m-d H:i:s', time() + $delay) : $now;
            $status = $delay ? 'delayed' : 'pending';

            $stmt = $this->db->prepare(
                "INSERT INTO {$this->tableName} (
                    queue, task, payload, priority, status,
                    attempts, max_attempts, timeout, backoff_strategy,
                    retry_delay, tags, created_at, updated_at, available_at
                ) VALUES (
                    :queue, :task, :payload, :priority, :status,
                    0, :max_attempts, :timeout, :backoff_strategy,
                    :retry_delay, :tags, :created_at, :updated_at, :available_at
                )",
            );

            $result = $stmt->execute([
                ':queue' => $queue,
                ':task' => $jobClass,
                ':payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                ':priority' => max(0, min(10, $priority)),
                ':status' => $status,
                ':max_attempts' => $options['max_attempts'] ?? 3,
                ':timeout' => $options['timeout'] ?? 300,
                ':backoff_strategy' =>
                    $options['backoff_strategy'] ?? 'exponential',
                ':retry_delay' => $options['retry_delay'] ?? 60,
                ':tags' => json_encode($options['tags'] ?? []),
                ':created_at' => $now,
                ':updated_at' => $now,
                ':available_at' => $availableAt,
            ]);

            if ($result) {
                $this->logger->debug(
                    'Job added to queue',
                    [
                        'queue' => $queue,
                        'task' => $jobClass,
                        'priority' => $priority,
                        'delay' => $delay,
                    ],
                    null,
                    $queue,
                    $jobClass,
                );
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to add job to queue: {$e->getMessage()}",
                [
                    'task' => $jobClass,
                    'queue' => $queue,
                ],
            );
            return false;
        }
    }

    abstract public function process(?string $queue = null): void;

    abstract protected function getCreateTableSql(): string;

    abstract protected function tableExists(): bool;

    protected function executeJob(array $job): void
    {
        // Delegate job execution to the executor (SRP)
        $this->executor->execute($job);

        // Mark as completed
        $completeStmt = $this->db->prepare(
            "UPDATE {$this->tableName} SET status = 'completed', updated_at = :updated_at WHERE id = :id",
        );
        $completeStmt->execute([
            ':id' => $job['id'],
            ':updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function handleJobFailure(array $job, \Throwable $exception): void
    {
        $attempts = $job['attempts'] + 1;
        $maxAttempts = $job['max_attempts'];

        // Log the error
        $this->logger->error(
            "Job failed: {$exception->getMessage()}",
            [
                'job_id' => $job['id'],
                'queue' => $job['queue'],
                'task' => $job['task'],
                'attempt' => $attempts,
                'max_attempts' => $maxAttempts,
                'exception' => get_class($exception),
            ],
            $job['id'],
            $job['queue'],
            $job['task'],
        );

        if ($attempts >= $maxAttempts) {
            // Move to dead letter queue
            $this->moveToDeadLetterQueue($job, $exception);

            // Delete from main queue
            $this->deleteJob($job['id']);

            $this->logger->warning(
                "Job moved to dead letter queue after {$attempts} attempts",
                ['job_id' => $job['id'], 'task' => $job['task']],
                $job['id'],
                $job['queue'],
                $job['task'],
            );
        } else {
            // Calculate retry delay with backoff
            $delay = RetryStrategy::calculateDelay(
                $job['backoff_strategy'],
                (int) $job['retry_delay'],
                $attempts,
            );

            $availableAt = date('Y-m-d H:i:s', time() + $delay);

            // Schedule retry
            $stmt = $this->db->prepare(
                "UPDATE {$this->tableName}
                 SET status = 'delayed',
                     attempts = :attempts,
                     last_error = :error,
                     available_at = :available_at,
                     updated_at = :updated_at
                 WHERE id = :id",
            );

            $stmt->execute([
                ':id' => $job['id'],
                ':attempts' => $attempts,
                ':error' => mb_substr($exception->getMessage(), 0, 1000),
                ':available_at' => $availableAt,
                ':updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logger->info(
                'Job scheduled for retry in ' .
                    RetryStrategy::formatDelay($delay),
                [
                    'job_id' => $job['id'],
                    'attempt' => $attempts,
                    'max_attempts' => $maxAttempts,
                    'delay' => $delay,
                ],
                $job['id'],
                $job['queue'],
                $job['task'],
            );
        }
    }

    protected function moveToDeadLetterQueue(
        array $job,
        \Throwable $exception,
    ): void {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO failed_jobs (
                    queue, task, payload, exception, stack_trace,
                    failed_at, total_attempts, retry_history,
                    original_job_id, priority, tags
                ) VALUES (
                    :queue, :task, :payload, :exception, :stack_trace,
                    :failed_at, :total_attempts, :retry_history,
                    :original_job_id, :priority, :tags
                )',
            );

            $retryHistory = $this->getRetryHistory($job['id']);

            $stmt->execute([
                ':queue' => $job['queue'],
                ':task' => $job['task'],
                ':payload' => $job['payload'],
                ':exception' => $exception->getMessage(),
                ':stack_trace' => $exception->getTraceAsString(),
                ':failed_at' => date('Y-m-d H:i:s'),
                ':total_attempts' => $job['attempts'] + 1,
                ':retry_history' => json_encode($retryHistory),
                ':original_job_id' => $job['id'],
                ':priority' => $job['priority'],
                ':tags' => $job['tags'] ?? '[]',
            ]);
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to move job to DLQ: {$e->getMessage()}",
                ['job_id' => $job['id']],
            );
        }
    }

    protected function getRetryHistory(int $jobId): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT level, message, created_at
                 FROM job_logs
                 WHERE job_id = :job_id AND level IN ('error', 'warning')
                 ORDER BY created_at ASC",
            );

            $stmt->execute([':job_id' => $jobId]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    protected function deleteJob(int $jobId): void
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->tableName} WHERE id = :id",
            );
            $stmt->execute([':id' => $jobId]);
        } catch (\Exception $e) {
            $this->logger->error("Failed to delete job: {$e->getMessage()}", [
                'job_id' => $jobId,
            ]);
        }
    }

    public function releaseDelayedJobs(): int
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->tableName}
                 SET status = 'pending', updated_at = :updated_at
                 WHERE status = 'delayed'
                   AND (available_at IS NULL OR available_at <= :now)",
            );

            $stmt->execute([
                ':updated_at' => date('Y-m-d H:i:s'),
                ':now' => date('Y-m-d H:i:s'),
            ]);

            $released = $stmt->rowCount();

            if ($released > 0) {
                $this->logger->info("Released {$released} delayed jobs");
            }

            return $released;
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to release delayed jobs: {$e->getMessage()}",
            );
            return 0;
        }
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

        $this->logger->info('Starting batch processing', [
            'batch_size' => $batchSize,
            'queue' => $queue ?? 'all',
            'max_time' => $maxExecutionTime,
        ]);

        // Release delayed jobs first
        $this->releaseDelayedJobs();

        while ($processed < $batchSize) {
            // Check execution time limit
            if (time() - $startTime >= $maxExecutionTime) {
                $this->logger->warning(
                    'Batch processing stopped: time limit reached',
                );
                break;
            }

            // Claim next job
            $job = $this->claimNextJob($queue);

            if (!$job) {
                $this->logger->debug('No more jobs available');
                break;
            }

            // Process job
            try {
                $this->executeJob($job);
                $successful++;
                $this->logger->info(
                    "Job {$job['id']} completed successfully",
                    [
                        'job_id' => $job['id'],
                        'queue' => $job['queue'],
                    ],
                    $job['id'],
                    $job['queue'],
                    $job['task'],
                );
            } catch (\Throwable $e) {
                $failed++;
                $this->handleJobFailure($job, $e);
            }

            $processed++;
        }

        $executionTime = time() - $startTime;

        $this->logger->info('Batch completed', [
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed,
            'execution_time' => $executionTime . 's',
        ]);

        return [
            'processed' => $processed,
            'successful' => $successful,
            'failed' => $failed,
            'execution_time' => $executionTime,
        ];
    }

    abstract protected function claimNextJob(
        ?string $queue = null,
    ): array|false;

    public function releaseStuckJobs(): int
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->tableName}
                 SET status = 'pending', updated_at = :updated_at
                 WHERE status = 'processing'
                   AND attempts < max_attempts
                   AND updated_at < :timeout_threshold",
            );

            $timeoutThreshold = date('Y-m-d H:i:s', time() - $this->jobTimeout);

            $stmt->execute([
                ':updated_at' => date('Y-m-d H:i:s'),
                ':timeout_threshold' => $timeoutThreshold,
            ]);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log('Failed to release stuck jobs: ' . $e->getMessage());
            return 0;
        }
    }

    public function failExceededJobs(): int
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->tableName}
                 SET status = 'failed',
                     last_error = 'Exceeded maximum retry attempts',
                     updated_at = :updated_at
                 WHERE status = 'processing'
                   AND attempts >= max_attempts
                   AND updated_at < :timeout_threshold",
            );

            $timeoutThreshold = date('Y-m-d H:i:s', time() - $this->jobTimeout);

            $stmt->execute([
                ':updated_at' => date('Y-m-d H:i:s'),
                ':timeout_threshold' => $timeoutThreshold,
            ]);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log('Failed to fail exceeded jobs: ' . $e->getMessage());
            return 0;
        }
    }

    public function cleanup(int $olderThanDays = 7): int
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->tableName}
                 WHERE status IN ('completed', 'failed')
                   AND created_at < :threshold",
            );

            $threshold = date('Y-m-d H:i:s', time() - $olderThanDays * 86400);

            $stmt->execute([':threshold' => $threshold]);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log('Failed to cleanup old jobs: ' . $e->getMessage());
            return 0;
        }
    }

    protected function ensureJobsTableExists(): void
    {
        $migration = new QueueMigration($this->db);

        if ($migration->needsMigration()) {
            // Check if old table exists (for migration)
            if ($this->tableExists()) {
                $migration->migrateExistingTable();
            } else {
                // Fresh installation
                $migration->up();
            }
        }
    }

    protected function createIndices(): void
    {
        try {
            // Index for fetching pending jobs
            $this->db->exec(
                "CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_status_created
                 ON {$this->tableName}(status, created_at)",
            );

            // Index for cleanup queries
            $this->db->exec(
                "CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_status_created_cleanup
                 ON {$this->tableName}(status, created_at)
                 WHERE status IN ('completed', 'failed')",
            );

            // Index for stuck job detection
            $this->db->exec(
                "CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_processing_updated
                 ON {$this->tableName}(status, updated_at)
                 WHERE status = 'processing'",
            );
        } catch (\Exception $e) {
            // Indices are optional optimization, log but don't fail
            error_log('Warning: Failed to create indices: ' . $e->getMessage());
        }
    }

    public function getStats(?string $queue = null): array
    {
        try {
            $queueFilter = $queue ? 'AND queue = :queue' : '';

            // Get counts from main jobs table
            $stmt = $this->db->prepare(
                "SELECT
                    status,
                    COUNT(*) as count,
                    COALESCE(SUM(CASE WHEN priority >= 8 THEN 1 ELSE 0 END), 0) as high_priority,
                    COALESCE(AVG(attempts), 0) as avg_attempts
                 FROM {$this->tableName}
                 WHERE 1=1 {$queueFilter}
                 GROUP BY status",
            );

            if ($queue) {
                $stmt->execute([':queue' => $queue]);
            } else {
                $stmt->execute();
            }

            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Initialize stats
            $stats = [
                'pending' => 0,
                'processing' => 0,
                'delayed' => 0,
                'completed' => 0,
                'failed' => 0,
                'high_priority' => 0,
                'avg_attempts' => 0,
            ];

            $totalJobs = 0;
            $totalAttempts = 0;

            foreach ($results as $row) {
                $status = $row['status'];
                $count = (int) $row['count'];
                $stats[$status] = $count;
                $stats['high_priority'] += (int) $row['high_priority'];
                $totalJobs += $count;
                $totalAttempts += (float) $row['avg_attempts'] * $count;
            }

            if ($totalJobs > 0) {
                $stats['avg_attempts'] = round($totalAttempts / $totalJobs, 2);
            }

            // Get failed jobs count
            $failedStmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM failed_jobs WHERE 1=1 {$queueFilter}",
            );

            if ($queue) {
                $failedStmt->execute([':queue' => $queue]);
            } else {
                $failedStmt->execute();
            }

            $failedCount = $failedStmt->fetch(\PDO::FETCH_ASSOC);
            $stats['failed_total'] = (int) $failedCount['count'];

            $stats['total'] = $totalJobs;
            $stats['queue'] = $queue ?? 'all';

            return $stats;
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to get queue stats: {$e->getMessage()}",
            );
            return [
                'error' => $e->getMessage(),
                'queue' => $queue ?? 'all',
            ];
        }
    }

    public function getFailedJobs(int $limit = 50, int $offset = 0): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT
                    id,
                    queue,
                    task,
                    payload,
                    exception,
                    failed_at,
                    total_attempts,
                    priority,
                    tags
                 FROM failed_jobs
                 ORDER BY failed_at DESC
                 LIMIT :limit OFFSET :offset',
            );

            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();

            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Parse JSON fields
            foreach ($jobs as &$job) {
                $job['payload'] = json_decode($job['payload'], true);
                $job['tags'] = json_decode($job['tags'] ?? '[]', true);
            }

            return $jobs;
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to get failed jobs: {$e->getMessage()}",
            );
            return [];
        }
    }

    public function getFailedJob(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT
                    id,
                    queue,
                    task,
                    payload,
                    exception,
                    stack_trace,
                    failed_at,
                    total_attempts,
                    retry_history,
                    original_job_id,
                    priority,
                    tags
                 FROM failed_jobs
                 WHERE id = :id',
            );

            $stmt->execute([':id' => $id]);
            $job = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$job) {
                return null;
            }

            // Parse JSON fields
            $job['payload'] = json_decode($job['payload'], true);
            $job['tags'] = json_decode($job['tags'] ?? '[]', true);
            $job['retry_history'] = json_decode(
                $job['retry_history'] ?? '[]',
                true,
            );

            return $job;
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to get failed job: {$e->getMessage()}",
                ['job_id' => $id],
            );
            return null;
        }
    }

    public function retryFailedJob(int $id): bool
    {
        try {
            // Get the failed job
            $failedJob = $this->getFailedJob($id);

            if (!$failedJob) {
                $this->logger->warning('Failed job not found', [
                    'job_id' => $id,
                ]);
                return false;
            }

            $this->db->beginTransaction();

            // Re-add the job to the main queue
            $result = $this->add(
                $failedJob['task'],
                $failedJob['payload'],
                $failedJob['queue'],
                $failedJob['priority'],
                null, // no delay
                ['tags' => $failedJob['tags']],
            );

            if (!$result) {
                $this->db->rollBack();
                return false;
            }

            // Delete from failed_jobs table
            $deleteStmt = $this->db->prepare(
                'DELETE FROM failed_jobs WHERE id = :id',
            );
            $deleteStmt->execute([':id' => $id]);

            $this->db->commit();

            $this->logger->info('Failed job retried successfully', [
                'job_id' => $id,
                'task' => $failedJob['task'],
                'queue' => $failedJob['queue'],
            ]);

            return true;
        } catch (\Exception $e) {
            try {
                $this->db->rollBack();
            } catch (\Exception $rollbackException) {
                // Already rolled back
            }

            $this->logger->error("Failed to retry job: {$e->getMessage()}", [
                'job_id' => $id,
            ]);
            return false;
        }
    }

    public function retryAllFailedJobs(?string $queue = null): int
    {
        try {
            $queueFilter = $queue ? 'WHERE queue = :queue' : '';

            // Get all failed jobs
            $stmt = $this->db->prepare(
                "SELECT id FROM failed_jobs {$queueFilter} ORDER BY failed_at ASC",
            );

            if ($queue) {
                $stmt->execute([':queue' => $queue]);
            } else {
                $stmt->execute();
            }

            $failedJobIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $retried = 0;

            foreach ($failedJobIds as $jobId) {
                if ($this->retryFailedJob($jobId)) {
                    $retried++;
                }
            }

            $this->logger->info("Retried {$retried} failed jobs", [
                'queue' => $queue ?? 'all',
            ]);

            return $retried;
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to retry all jobs: {$e->getMessage()}",
            );
            return 0;
        }
    }

    public function purgeOldJobs(int $olderThanDays = 7): int
    {
        try {
            $this->db->beginTransaction();

            // Delete from main jobs table
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->tableName}
                 WHERE status IN ('completed', 'failed')
                   AND updated_at < :threshold",
            );

            $threshold = date('Y-m-d H:i:s', time() - $olderThanDays * 86400);
            $stmt->execute([':threshold' => $threshold]);

            $deletedJobs = $stmt->rowCount();

            // Delete from failed_jobs table
            $failedStmt = $this->db->prepare(
                'DELETE FROM failed_jobs WHERE failed_at < :threshold',
            );
            $failedStmt->execute([':threshold' => $threshold]);

            $deletedFailed = $failedStmt->rowCount();

            $this->db->commit();

            $total = $deletedJobs + $deletedFailed;

            $this->logger->info("Purged {$total} old jobs", [
                'older_than_days' => $olderThanDays,
                'from_jobs_table' => $deletedJobs,
                'from_failed_table' => $deletedFailed,
            ]);

            return $total;
        } catch (\Exception $e) {
            try {
                $this->db->rollBack();
            } catch (\Exception $rollbackException) {
                // Already rolled back
            }

            $this->logger->error(
                "Failed to purge old jobs: {$e->getMessage()}",
            );
            return 0;
        }
    }

    public function purgeFailedJobs(?string $queue = null): int
    {
        try {
            $queueFilter = $queue ? 'WHERE queue = :queue' : '';

            $stmt = $this->db->prepare(
                "DELETE FROM failed_jobs {$queueFilter}",
            );

            if ($queue) {
                $stmt->execute([':queue' => $queue]);
            } else {
                $stmt->execute();
            }

            $deleted = $stmt->rowCount();

            $this->logger->info("Purged {$deleted} failed jobs", [
                'queue' => $queue ?? 'all',
            ]);

            return $deleted;
        } catch (\Exception $e) {
            $this->logger->error(
                "Failed to purge failed jobs: {$e->getMessage()}",
            );
            return 0;
        }
    }
}
