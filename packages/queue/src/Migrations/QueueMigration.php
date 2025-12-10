<?php

declare(strict_types=1);

namespace Lalaz\Queue\Migrations;

use Lalaz\Database\Connection;
use Lalaz\Support\Errors;

class QueueMigration
{
    private Connection $db;
    private string $driver;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $this->driver = $this->detectDriver();
    }

    public function up(): void
    {
        $this->createJobsTable();
        $this->createFailedJobsTable();
        $this->createJobLogsTable();
        $this->createIndices();
    }

    public function down(): void
    {
        $this->db->exec('DROP TABLE IF EXISTS job_logs');
        $this->db->exec('DROP TABLE IF EXISTS failed_jobs');
        $this->db->exec('DROP TABLE IF EXISTS jobs');
    }

    public function needsMigration(): bool
    {
        // Check if jobs table exists
        if (!$this->tableExists('jobs')) {
            return true;
        }

        // Check if table has new columns
        $columns = $this->getTableColumns('jobs');
        $requiredColumns = [
            'queue',
            'priority',
            'available_at',
            'reserved_at',
            'backoff_strategy',
        ];

        foreach ($requiredColumns as $column) {
            if (!in_array($column, $columns)) {
                return true;
            }
        }

        // Check if failed_jobs exists
        if (!$this->tableExists('failed_jobs')) {
            return true;
        }

        // Check if job_logs exists
        if (!$this->tableExists('job_logs')) {
            return true;
        }

        return false;
    }

    public function migrateExistingTable(): void
    {
        if (!$this->tableExists('jobs')) {
            return;
        }

        $columns = $this->getTableColumns('jobs');

        // Add missing columns
        $migrations = [
            'queue' =>
                "ALTER TABLE jobs ADD COLUMN queue VARCHAR(50) NOT NULL DEFAULT 'default'",
            'priority' =>
                'ALTER TABLE jobs ADD COLUMN priority INTEGER NOT NULL DEFAULT 5',
            'retry_delay' =>
                'ALTER TABLE jobs ADD COLUMN retry_delay INTEGER NOT NULL DEFAULT 60',
            'backoff_strategy' =>
                "ALTER TABLE jobs ADD COLUMN backoff_strategy VARCHAR(20) DEFAULT 'exponential'",
            'available_at' =>
                'ALTER TABLE jobs ADD COLUMN available_at TIMESTAMP',
            'reserved_at' =>
                'ALTER TABLE jobs ADD COLUMN reserved_at TIMESTAMP',
            'timeout' =>
                'ALTER TABLE jobs ADD COLUMN timeout INTEGER DEFAULT 300',
            'tags' => 'ALTER TABLE jobs ADD COLUMN tags TEXT',
        ];

        foreach ($migrations as $column => $sql) {
            if (!in_array($column, $columns)) {
                try {
                    $this->db->exec($sql);
                } catch (\Exception $e) {
                    error_log(
                        "Failed to add column {$column}: {$e->getMessage()}",
                    );
                }
            }
        }
    }

    private function createJobsTable(): void
    {
        $sql = match ($this->driver) {
            'sqlite' => $this->getJobsTableSqlite(),
            'mysql' => $this->getJobsTableMysql(),
            'pgsql' => $this->getJobsTablePostgres(),
            default => Errors::throwNotSupported(
                "queue driver '{$this->driver}'",
                ['driver' => $this->driver],
            ),
        };

        $this->db->exec($sql);
    }

    private function createFailedJobsTable(): void
    {
        $sql = match ($this->driver) {
            'sqlite' => $this->getFailedJobsTableSqlite(),
            'mysql' => $this->getFailedJobsTableMysql(),
            'pgsql' => $this->getFailedJobsTablePostgres(),
            default => Errors::throwNotSupported(
                "queue driver '{$this->driver}'",
                ['driver' => $this->driver],
            ),
        };

        $this->db->exec($sql);
    }

    private function createJobLogsTable(): void
    {
        $sql = match ($this->driver) {
            'sqlite' => $this->getJobLogsTableSqlite(),
            'mysql' => $this->getJobLogsTableMysql(),
            'pgsql' => $this->getJobLogsTablePostgres(),
            default => Errors::throwNotSupported(
                "queue driver '{$this->driver}'",
                ['driver' => $this->driver],
            ),
        };

        $this->db->exec($sql);
    }

    private function createIndices(): void
    {
        $indices = [
            // Jobs table indices
            'CREATE INDEX IF NOT EXISTS idx_jobs_queue_status_priority
             ON jobs(queue, status, priority DESC, available_at ASC)',

            'CREATE INDEX IF NOT EXISTS idx_jobs_status_available
             ON jobs(status, available_at)',

            'CREATE INDEX IF NOT EXISTS idx_jobs_status_reserved
             ON jobs(status, reserved_at)',

            'CREATE INDEX IF NOT EXISTS idx_jobs_cleanup
             ON jobs(status, created_at)',

            // Failed jobs indices
            'CREATE INDEX IF NOT EXISTS idx_failed_jobs_queue
             ON failed_jobs(queue, failed_at DESC)',

            'CREATE INDEX IF NOT EXISTS idx_failed_jobs_task
             ON failed_jobs(task, failed_at DESC)',

            // Job logs indices
            'CREATE INDEX IF NOT EXISTS idx_job_logs_job_id
             ON job_logs(job_id, created_at)',

            'CREATE INDEX IF NOT EXISTS idx_job_logs_level
             ON job_logs(level, created_at DESC)',

            'CREATE INDEX IF NOT EXISTS idx_job_logs_queue
             ON job_logs(queue, created_at DESC)',
        ];

        foreach ($indices as $sql) {
            try {
                $this->db->exec($sql);
            } catch (\Exception $e) {
                // Indices are optional optimization
                error_log(
                    "Warning: Failed to create index: {$e->getMessage()}",
                );
            }
        }
    }

    private function getJobsTableSqlite(): string
    {
        return "CREATE TABLE IF NOT EXISTS jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(50) NOT NULL DEFAULT 'default',
            priority INTEGER NOT NULL DEFAULT 5,
            task VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INTEGER NOT NULL DEFAULT 0,
            max_attempts INTEGER NOT NULL DEFAULT 3,
            retry_delay INTEGER NOT NULL DEFAULT 60,
            backoff_strategy VARCHAR(20) DEFAULT 'exponential',
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP,
            available_at TIMESTAMP,
            reserved_at TIMESTAMP,
            last_error TEXT,
            timeout INTEGER DEFAULT 300,
            tags TEXT
        )";
    }

    private function getJobsTableMysql(): string
    {
        return "CREATE TABLE IF NOT EXISTS jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue VARCHAR(50) NOT NULL DEFAULT 'default',
            priority INT NOT NULL DEFAULT 5,
            task VARCHAR(255) NOT NULL,
            payload LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 3,
            retry_delay INT NOT NULL DEFAULT 60,
            backoff_strategy VARCHAR(20) DEFAULT 'exponential',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            available_at TIMESTAMP NULL DEFAULT NULL,
            reserved_at TIMESTAMP NULL DEFAULT NULL,
            last_error TEXT,
            timeout INT DEFAULT 300,
            tags TEXT,
            INDEX idx_queue_status_priority (queue, status, priority DESC, available_at ASC),
            INDEX idx_status_available (status, available_at),
            INDEX idx_status_reserved (status, reserved_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    }

    private function getJobsTablePostgres(): string
    {
        return "CREATE TABLE IF NOT EXISTS jobs (
            id BIGSERIAL PRIMARY KEY,
            queue VARCHAR(50) NOT NULL DEFAULT 'default',
            priority INTEGER NOT NULL DEFAULT 5,
            task VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            attempts INTEGER NOT NULL DEFAULT 0,
            max_attempts INTEGER NOT NULL DEFAULT 3,
            retry_delay INTEGER NOT NULL DEFAULT 60,
            backoff_strategy VARCHAR(20) DEFAULT 'exponential',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP,
            available_at TIMESTAMP,
            reserved_at TIMESTAMP,
            last_error TEXT,
            timeout INTEGER DEFAULT 300,
            tags TEXT
        )";
    }

    private function getFailedJobsTableSqlite(): string
    {
        return 'CREATE TABLE IF NOT EXISTS failed_jobs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            queue VARCHAR(50) NOT NULL,
            task VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            exception TEXT NOT NULL,
            stack_trace TEXT,
            failed_at TIMESTAMP NOT NULL,
            total_attempts INTEGER NOT NULL,
            retry_history TEXT,
            original_job_id INTEGER,
            priority INTEGER,
            tags TEXT
        )';
    }

    private function getFailedJobsTableMysql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS failed_jobs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue VARCHAR(50) NOT NULL,
            task VARCHAR(255) NOT NULL,
            payload LONGTEXT NOT NULL,
            exception TEXT NOT NULL,
            stack_trace TEXT,
            failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            total_attempts INT NOT NULL,
            retry_history TEXT,
            original_job_id BIGINT UNSIGNED,
            priority INT,
            tags TEXT,
            INDEX idx_queue (queue, failed_at DESC),
            INDEX idx_task (task, failed_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    private function getFailedJobsTablePostgres(): string
    {
        return 'CREATE TABLE IF NOT EXISTS failed_jobs (
            id BIGSERIAL PRIMARY KEY,
            queue VARCHAR(50) NOT NULL,
            task VARCHAR(255) NOT NULL,
            payload TEXT NOT NULL,
            exception TEXT NOT NULL,
            stack_trace TEXT,
            failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            total_attempts INTEGER NOT NULL,
            retry_history TEXT,
            original_job_id BIGINT,
            priority INTEGER,
            tags TEXT
        )';
    }

    private function getJobLogsTableSqlite(): string
    {
        return 'CREATE TABLE IF NOT EXISTS job_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            job_id INTEGER NOT NULL,
            queue VARCHAR(50) NOT NULL,
            task VARCHAR(255) NOT NULL,
            level VARCHAR(10) NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            created_at TIMESTAMP NOT NULL,
            memory_usage INTEGER,
            execution_time REAL
        )';
    }

    private function getJobLogsTableMysql(): string
    {
        return 'CREATE TABLE IF NOT EXISTS job_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            job_id BIGINT UNSIGNED NOT NULL,
            queue VARCHAR(50) NOT NULL,
            task VARCHAR(255) NOT NULL,
            level VARCHAR(10) NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            memory_usage BIGINT UNSIGNED,
            execution_time DECIMAL(10, 4),
            INDEX idx_job_id (job_id, created_at),
            INDEX idx_level (level, created_at DESC),
            INDEX idx_queue (queue, created_at DESC)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
    }

    private function getJobLogsTablePostgres(): string
    {
        return 'CREATE TABLE IF NOT EXISTS job_logs (
            id BIGSERIAL PRIMARY KEY,
            job_id BIGINT NOT NULL,
            queue VARCHAR(50) NOT NULL,
            task VARCHAR(255) NOT NULL,
            level VARCHAR(10) NOT NULL,
            message TEXT NOT NULL,
            context TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            memory_usage BIGINT,
            execution_time NUMERIC(10, 4)
        )';
    }

    private function detectDriver(): string
    {
        $driverName = $this->db->getDriverName();

        return match ($driverName) {
            'sqlite' => 'sqlite',
            'mysql' => 'mysql',
            'pgsql', 'postgres' => 'pgsql',
            default => Errors::throwNotSupported(
                "queue driver '{$driverName}'",
                ['driver' => $driverName],
            ),
        };
    }

    private function tableExists(string $tableName): bool
    {
        try {
            $sql = match ($this->driver) {
                'sqlite'
                    => "SELECT name FROM sqlite_master WHERE type='table' AND name=:table",
                'mysql'
                    => 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table',
                'pgsql'
                    => "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename = :table",
                default => Errors::throwNotSupported(
                    "queue driver '{$this->driver}'",
                    ['driver' => $this->driver],
                ),
            };

            $stmt = $this->db->prepare($sql);
            $stmt->execute([':table' => $tableName]);
            return $stmt->fetch() !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getTableColumns(string $tableName): array
    {
        try {
            $sql = match ($this->driver) {
                'sqlite' => "PRAGMA table_info({$tableName})",
                'mysql' => "SHOW COLUMNS FROM {$tableName}",
                'pgsql'
                    => "SELECT column_name FROM information_schema.columns WHERE table_name = '{$tableName}'",
                default => Errors::throwNotSupported(
                    "queue driver '{$this->driver}'",
                    ['driver' => $this->driver],
                ),
            };

            $stmt = $this->db->query($sql);
            $columns = [];

            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $columns[] = match ($this->driver) {
                    'sqlite' => $row['name'],
                    'mysql' => $row['Field'],
                    'pgsql' => $row['column_name'],
                    default => null,
                };
            }

            return $columns;
        } catch (\Exception $e) {
            return [];
        }
    }
}
