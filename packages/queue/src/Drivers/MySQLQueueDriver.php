<?php

declare(strict_types=1);

namespace Lalaz\Queue\Drivers;

use PDO;

class MySQLQueueDriver extends AbstractDatabaseQueueDriver
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

            // Lock and fetch a pending job
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE status = 'pending'
                   AND (available_at IS NULL OR available_at <= :now)
                   {$queueFilter}
                 ORDER BY priority DESC, created_at ASC
                 LIMIT 1
                 FOR UPDATE SKIP LOCKED",
            );

            $params = [':now' => date('Y-m-d H:i:s')];
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
                     reserved_at = :reserved_at,
                     updated_at = :updated_at
                 WHERE id = :id",
            );

            $timestamp = date('Y-m-d H:i:s');
            $updateStmt->execute([
                ':id' => $job['id'],
                ':reserved_at' => $timestamp,
                ':updated_at' => $timestamp,
            ]);

            $this->db->commit();

            // Update local job data
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
            $stmt = $this->db->prepare('SHOW TABLES LIKE :table');
            $stmt->execute([':table' => $this->tableName]);
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getCreateTableSql(): string
    {
        return "
            CREATE TABLE {$this->tableName} (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue VARCHAR(50) DEFAULT 'default',
                task VARCHAR(255) NOT NULL,
                payload TEXT,
                status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                priority INT DEFAULT 0,
                attempts INT UNSIGNED DEFAULT 0,
                max_attempts INT UNSIGNED DEFAULT 3,
                last_error TEXT NULL,
                available_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status_priority_created (status, priority DESC, created_at ASC),
                INDEX idx_processing_updated (status, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ";
    }

    protected function createIndices(): void
    {
        // Indices are already created in CREATE TABLE for MySQL
        // This method exists for consistency with the abstract class
        return;
    }
}
