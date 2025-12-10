<?php

declare(strict_types=1);

namespace Lalaz\Queue\Drivers;

use PDO;

class PostgresQueueDriver extends AbstractDatabaseQueueDriver
{
    public function process(?string $queue = null): void
    {
        // First, release any stuck jobs
        $this->releaseStuckJobs();
        $this->failExceededJobs();

        // Claim and process a job
        $job = $this->claimNextJob($queue);

        if (!$job) {
            return; // No jobs available
        }

        try {
            $this->executeJob($job);
        } catch (\Throwable $e) {
            $this->handleJobFailure($job, $e);
        }
    }

    protected function claimNextJob(?string $queue = null): array|false
    {
        try {
            $this->db->beginTransaction();

            $queueFilter = $queue ? 'AND queue = :queue' : '';

            // Lock and fetch a pending job, skipping already locked rows
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE status = 'pending'
                   AND (available_at IS NULL OR available_at <= CURRENT_TIMESTAMP)
                   {$queueFilter}
                 ORDER BY priority DESC, created_at ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED",
            );

            $params = [];
            if ($queue) {
                $params[':queue'] = $queue;
            }

            $stmt->execute($params);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$job) {
                $this->db->commit();
                return false;
            }

            // Mark as processing
            $updateStmt = $this->db->prepare(
                "UPDATE {$this->tableName}
                 SET status = 'processing',
                     attempts = attempts + 1,
                     reserved_at = CURRENT_TIMESTAMP,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id",
            );

            $updateStmt->execute([':id' => $job['id']]);

            $this->db->commit();

            // Update local job data
            $timestamp = date('Y-m-d H:i:s');
            $job['status'] = 'processing';
            $job['attempts'] = ($job['attempts'] ?? 0) + 1;
            $job['reserved_at'] = $timestamp;
            $job['updated_at'] = $timestamp;

            return $job;
        } catch (\Exception $e) {
            try {
                $this->db->rollBack();
            } catch (\Exception $rollbackException) {
                // Already rolled back or no transaction
            }

            error_log('Failed to claim job: ' . $e->getMessage());
            return false;
        }
    }

    protected function tableExists(): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT EXISTS (
                    SELECT FROM information_schema.tables
                    WHERE table_schema = 'public'
                    AND table_name = :table
                )",
            );
            $stmt->execute([':table' => $this->tableName]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getCreateTableSql(): string
    {
        return "
            CREATE TABLE {$this->tableName} (
                id BIGSERIAL PRIMARY KEY,
                queue VARCHAR(50) DEFAULT 'default',
                task VARCHAR(255) NOT NULL,
                payload TEXT,
                status VARCHAR(20) DEFAULT 'pending' CHECK(status IN ('pending', 'processing', 'completed', 'failed')),
                priority INTEGER DEFAULT 0,
                attempts INTEGER DEFAULT 0,
                max_attempts INTEGER DEFAULT 3,
                last_error TEXT NULL,
                available_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
    }

    protected function createIndices(): void
    {
        try {
            // Composite index for efficient job claiming
            $this->db->exec(
                "CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_status_priority_created
                 ON {$this->tableName}(status, priority DESC, created_at ASC)
                 WHERE status = 'pending'",
            );

            // Partial index for stuck job detection
            $this->db->exec(
                "CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_processing_updated
                 ON {$this->tableName}(updated_at)
                 WHERE status = 'processing'",
            );

            // Partial index for cleanup queries
            $this->db->exec(
                "CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_cleanup
                 ON {$this->tableName}(created_at)
                 WHERE status IN ('completed', 'failed')",
            );

            // Index for queue-based job distribution
            $this->db->exec(
                "CREATE INDEX IF NOT EXISTS idx_{$this->tableName}_queue
                 ON {$this->tableName}(queue, status)",
            );
        } catch (\Exception $e) {
            // Indices are optional optimization, log but don't fail
            error_log(
                'Warning: Failed to create PostgreSQL indices: ' .
                    $e->getMessage(),
            );
        }
    }

    public function releaseStuckJobs(): int
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE {$this->tableName}
                 SET status = 'pending', updated_at = CURRENT_TIMESTAMP
                 WHERE status = 'processing'
                   AND attempts < max_attempts
                   AND updated_at < (CURRENT_TIMESTAMP - make_interval(secs => :timeout))",
            );

            $stmt->execute([':timeout' => $this->jobTimeout]);

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
                     updated_at = CURRENT_TIMESTAMP
                 WHERE status = 'processing'
                   AND attempts >= max_attempts
                   AND updated_at < (CURRENT_TIMESTAMP - make_interval(secs => :timeout))",
            );

            $stmt->execute([':timeout' => $this->jobTimeout]);

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
                   AND created_at < (CURRENT_TIMESTAMP - make_interval(days => :days))",
            );

            $stmt->execute([':days' => $olderThanDays]);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log('Failed to cleanup old jobs: ' . $e->getMessage());
            return 0;
        }
    }
}
