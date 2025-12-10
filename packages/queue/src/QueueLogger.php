<?php

declare(strict_types=1);

namespace Lalaz\Queue;

use Lalaz\Config\Config;
use Lalaz\Database\Connection;
use Lalaz\Queue\Contracts\QueueLoggerInterface;

/**
 * Default queue logger implementation with database persistence.
 *
 * Following DIP - implements QueueLoggerInterface for easy replacement.
 */
class QueueLogger implements QueueLoggerInterface
{
    private Connection $db;
    private string $tableName = 'job_logs';
    private bool $consoleOutput;

    public function __construct(Connection $db)
    {
        $this->db = $db;
        $this->consoleOutput = php_sapi_name() === 'cli';
    }

    public function log(
        string $level,
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null,
    ): void {
        // Skip debug messages if debug mode is off
        if ($level === 'debug' && !Config::isDevelopment()) {
            return;
        }

        // Always log to PHP error_log
        $formattedMessage = $this->formatMessage($level, $message, $context);
        error_log($formattedMessage);

        // Log to database for persistence
        $this->logToDatabase($level, $message, $context, $jobId, $queue, $task);

        // Output to console if in CLI mode and appropriate level
        if ($this->consoleOutput) {
            $this->outputToConsole($level, $message, $context);
        }
    }

    public function debug(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null,
    ): void {
        $this->log('debug', $message, $context, $jobId, $queue, $task);
    }

    public function info(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null,
    ): void {
        $this->log('info', $message, $context, $jobId, $queue, $task);
    }

    public function warning(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null,
    ): void {
        $this->log('warning', $message, $context, $jobId, $queue, $task);
    }

    public function error(
        string $message,
        array $context = [],
        ?int $jobId = null,
        ?string $queue = null,
        ?string $task = null,
    ): void {
        $this->log('error', $message, $context, $jobId, $queue, $task);
    }

    private function logToDatabase(
        string $level,
        string $message,
        array $context,
        ?int $jobId,
        ?string $queue,
        ?string $task,
    ): void {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->tableName} (
                    job_id, queue, task, level, message, context,
                    memory_usage, created_at
                ) VALUES (
                    :job_id, :queue, :task, :level, :message, :context,
                    :memory_usage, :created_at
                )",
            );

            $stmt->execute([
                ':job_id' => $jobId,
                ':queue' => $queue,
                ':task' => $task,
                ':level' => $level,
                ':message' => mb_substr($message, 0, 5000),
                ':context' => json_encode($context),
                ':memory_usage' => memory_get_usage(true),
                ':created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            // Don't fail job if logging fails
            error_log("Failed to log to database: {$e->getMessage()}");
        }
    }

    private function formatMessage(
        string $level,
        string $message,
        array $context,
    ): string {
        $contextStr = $context ? ' ' . json_encode($context) : '';
        return '[Queue][' . strtoupper($level) . "] {$message}{$contextStr}";
    }

    private function outputToConsole(
        string $level,
        string $message,
        array $context,
    ): void {
        $color = match ($level) {
            'error' => "\033[31m", // Red
            'warning' => "\033[33m", // Yellow
            'info' => "\033[36m", // Cyan
            'debug' => "\033[90m", // Gray
            default => "\033[0m", // Default
        };

        $reset = "\033[0m";
        $timestamp = date('Y-m-d H:i:s');
        $levelUpper = strtoupper($level);

        echo "{$color}[{$timestamp}][{$levelUpper}]{$reset} {$message}\n";

        if ($context && Config::isDevelopment()) {
            echo "{$color}Context:{$reset} " .
                json_encode($context, JSON_PRETTY_PRINT) .
                "\n";
        }
    }

    public function logJobMetrics(
        int $jobId,
        string $queue,
        string $task,
        float $executionTime,
        int $memoryUsage,
    ): void {
        $this->info(
            'Job execution completed',
            [
                'execution_time' => round($executionTime, 2) . 's',
                'memory_usage' => $this->formatBytes($memoryUsage),
            ],
            $jobId,
            $queue,
            $task,
        );

        // Update the last log entry with metrics
        try {
            $this->db->exec(
                "UPDATE {$this->tableName}
                 SET execution_time = {$executionTime},
                     memory_usage = {$memoryUsage}
                 WHERE job_id = {$jobId}
                   AND level = 'info'
                 ORDER BY created_at DESC
                 LIMIT 1",
            );
        } catch (\Exception $e) {
            error_log("Failed to update job metrics: {$e->getMessage()}");
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function getJobLogs(int $jobId, int $limit = 50): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE job_id = :job_id
                 ORDER BY created_at DESC
                 LIMIT :limit",
            );

            $stmt->execute([
                ':job_id' => $jobId,
                ':limit' => $limit,
            ]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Failed to get job logs: {$e->getMessage()}");
            return [];
        }
    }

    public function getLogsByLevel(string $level, int $limit = 100): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->tableName}
                 WHERE level = :level
                 ORDER BY created_at DESC
                 LIMIT :limit",
            );

            $stmt->execute([
                ':level' => $level,
                ':limit' => $limit,
            ]);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Failed to get logs by level: {$e->getMessage()}");
            return [];
        }
    }

    public function cleanup(int $olderThanDays = 30): int
    {
        try {
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->tableName}
                 WHERE created_at < :threshold",
            );

            $threshold = date('Y-m-d H:i:s', time() - $olderThanDays * 86400);

            $stmt->execute([':threshold' => $threshold]);

            return $stmt->rowCount();
        } catch (\Exception $e) {
            error_log("Failed to cleanup logs: {$e->getMessage()}");
            return 0;
        }
    }
}
