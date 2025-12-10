<?php

declare(strict_types=1);

namespace Lalaz\Queue\Drivers;

use Lalaz\Database\Connection;
use Lalaz\Queue\Contracts\JobExecutorInterface;
use Lalaz\Queue\Contracts\QueueLoggerInterface;
use PDO;

class SQLiteQueueDriver extends AbstractDatabaseQueueDriver
{
    private bool $supportsReturning;

    public function __construct(
        Connection $db,
        ?JobExecutorInterface $executor = null,
        ?QueueLoggerInterface $logger = null
    ) {
        parent::__construct($db, $executor, $logger);
        $this->supportsReturning = $this->checkReturningSupport();
    }

    private function checkReturningSupport(): bool
    {
        try {
            $version = $this->db
                ->query('SELECT sqlite_version()')
                ->fetchColumn();
            return version_compare($version, '3.35.0', '>=');
        } catch (\Exception $e) {
            return false;
        }
    }

    public function process(?string $queue = null): void
    {
        // Release delayed jobs first
        $this->releaseDelayedJobs();

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
        if ($this->supportsReturning) {
            return $this->claimJobWithReturning($queue);
        }

        return $this->claimJobWithTransaction($queue);
    }

    private function claimJobWithReturning(?string $queue): array|false
    {
        try {
            $queueFilter = $queue ? 'AND queue = :queue' : '';

            $stmt = $this->db->prepare(
                "UPDATE {$this->tableName}
                 SET status = 'processing',
                     attempts = attempts + 1,
                     reserved_at = :reserved_at,
                     updated_at = :updated_at
                 WHERE id = (
                     SELECT id FROM {$this->tableName}
                     WHERE status = 'pending'
                       AND (available_at IS NULL OR available_at <= :now)
                       {$queueFilter}
                     ORDER BY priority DESC, created_at ASC
                     LIMIT 1
                 )
                 RETURNING *",
            );

            $params = [
                ':reserved_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
                ':now' => date('Y-m-d H:i:s'),
            ];

            if ($queue) {
                $params[':queue'] = $queue;
            }

            $stmt->execute($params);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);

            return $job ?: false;
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to claim job with RETURNING: ' . $e->getMessage(),
            );
            return false;
        }
    }

    private function claimJobWithTransaction(?string $queue): array|false
    {
        try {
            // Start IMMEDIATE transaction to get write lock
            $this->db->exec('BEGIN IMMEDIATE');

            $queueFilter = $queue ? 'AND queue = :queue' : '';

            // Find a pending job
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE status = 'pending'
                   AND (available_at IS NULL OR available_at <= :now)
                   {$queueFilter}
                 ORDER BY priority DESC, created_at ASC
                 LIMIT 1",
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

            // Mark it as processing
            $updateStmt = $this->db->prepare(
                "UPDATE {$this->tableName}
                 SET status = 'processing',
                     attempts = attempts + 1,
                     reserved_at = :reserved_at,
                     updated_at = :updated_at
                 WHERE id = :id",
            );

            $updateStmt->execute([
                ':id' => $job['id'],
                ':reserved_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->commit();

            // Refresh job data with updated fields
            $job['status'] = 'processing';
            $job['attempts'] = ($job['attempts'] ?? 0) + 1;
            $job['reserved_at'] = date('Y-m-d H:i:s');
            $job['updated_at'] = date('Y-m-d H:i:s');

            return $job;
        } catch (\Exception $e) {
            try {
                $this->db->rollBack();
            } catch (\Exception $rollbackException) {
                // Already rolled back or no transaction
            }

            $this->logger->error(
                'Failed to claim job with transaction: ' . $e->getMessage(),
            );
            return false;
        }
    }

    protected function tableExists(): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=:table",
            );
            $stmt->execute([':table' => $this->tableName]);
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getCreateTableSql(): string
    {
        // Not used anymore - migration system handles this
        return '';
    }
}
