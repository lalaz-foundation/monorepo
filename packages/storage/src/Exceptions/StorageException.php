<?php

declare(strict_types=1);

namespace Lalaz\Storage\Exceptions;

use RuntimeException;

/**
 * StorageException
 *
 * Base exception for storage-related errors.
 *
 * @package lalaz/storage
 * @author  Elasticmind <ola@elasticmind.io>
 * @link    https://lalaz.dev
 */
class StorageException extends RuntimeException
{
    /**
     * Create exception for missing configuration.
     */
    public static function missingConfiguration(string $key): self
    {
        return new self("Storage configuration missing: {$key}");
    }

    /**
     * Create exception for invalid path.
     */
    public static function invalidPath(string $path, string $reason = ''): self
    {
        $message = "Invalid storage path: {$path}";
        if ($reason !== '') {
            $message .= " ({$reason})";
        }
        return new self($message);
    }

    /**
     * Create exception for file not found.
     */
    public static function fileNotFound(string $path): self
    {
        return new self("File not found in storage: {$path}");
    }

    /**
     * Create exception for directory not found.
     */
    public static function directoryNotFound(string $path): self
    {
        return new self("Directory not found in storage: {$path}");
    }

    /**
     * Create exception for path traversal attempt.
     */
    public static function pathTraversal(string $path): self
    {
        return new self("Path traversal detected: {$path}");
    }

    /**
     * Create exception for upload failure.
     */
    public static function uploadFailed(string $source, string $destination, string $reason = ''): self
    {
        $message = "Failed to upload file from '{$source}' to '{$destination}'";
        if ($reason !== '') {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    /**
     * Create exception for write failure.
     */
    public static function writeFailed(string $path, string $reason = ''): self
    {
        $message = "Failed to write to file: {$path}";
        if ($reason !== '') {
            $message .= " ({$reason})";
        }
        return new self($message);
    }

    /**
     * Create exception for read failure.
     */
    public static function readFailed(string $path, string $reason = ''): self
    {
        $message = "Failed to read file: {$path}";
        if ($reason !== '') {
            $message .= " ({$reason})";
        }
        return new self($message);
    }

    /**
     * Create exception for delete failure.
     */
    public static function deleteFailed(string $path, string $reason = ''): self
    {
        $message = "Failed to delete: {$path}";
        if ($reason !== '') {
            $message .= " ({$reason})";
        }
        return new self($message);
    }

    /**
     * Create exception for copy failure.
     */
    public static function copyFailed(string $from, string $to, string $reason = ''): self
    {
        $message = "Failed to copy from '{$from}' to '{$to}'";
        if ($reason !== '') {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    /**
     * Create exception for move failure.
     */
    public static function moveFailed(string $from, string $to, string $reason = ''): self
    {
        $message = "Failed to move from '{$from}' to '{$to}'";
        if ($reason !== '') {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    /**
     * Create exception for unknown driver.
     */
    public static function unknownDriver(string $driver): self
    {
        return new self("Unknown storage driver: {$driver}");
    }
}
