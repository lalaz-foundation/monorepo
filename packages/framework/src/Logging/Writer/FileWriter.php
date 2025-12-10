<?php

declare(strict_types=1);

namespace Lalaz\Logging\Writer;

use Lalaz\Exceptions\LoggingException;
use Lalaz\Logging\Contracts\FormatterInterface;
use Lalaz\Logging\Contracts\WriterInterface;

/**
 * File writer with automatic log rotation by size.
 *
 * Features:
 * - Automatic directory creation
 * - Size-based rotation (default 10MB)
 * - Configurable number of backup files
 * - Atomic writes with file locking
 * - Optional formatter for pre-formatted messages
 *
 *  * @package lalaz/framework
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */
final class FileWriter implements WriterInterface
{
    private string $path;
    private int $maxSize;
    private int $maxFiles;
    private ?FormatterInterface $formatter;

    /**
     * @param string $path Path to the log file
     * @param int $maxSize Maximum file size in bytes before rotation (default 10MB)
     * @param int $maxFiles Maximum number of rotated files to keep (default 5)
     * @param FormatterInterface|null $formatter Optional formatter for messages
     */
    public function __construct(
        string $path,
        int $maxSize = 10485760,
        int $maxFiles = 5,
        ?FormatterInterface $formatter = null,
    ) {
        $this->path = $path;
        $this->maxSize = $maxSize;
        $this->maxFiles = $maxFiles;
        $this->formatter = $formatter;

        $this->ensureDirectoryExists();
    }

    public function write(string $message): void
    {
        $this->rotateIfNeeded();

        $result = file_put_contents(
            $this->path,
            $message . PHP_EOL,
            FILE_APPEND | LOCK_EX,
        );

        if ($result === false) {
            throw LoggingException::writeError($this->path, 'Unable to write to log file');
        }
    }

    /**
     * Ensure the directory for the log file exists.
     */
    private function ensureDirectoryExists(): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw LoggingException::directoryCreationFailed($directory);
            }
        }

        if (!is_writable($directory)) {
            throw LoggingException::permissionDenied($directory, 'write');
        }
    }

    /**
     * Rotate log files if the current file exceeds max size.
     */
    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->path)) {
            return;
        }

        $size = @filesize($this->path);

        if ($size === false || $size < $this->maxSize) {
            return;
        }

        // Remove the oldest file if it exists
        $oldestFile = $this->path . '.' . $this->maxFiles;
        if (file_exists($oldestFile)) {
            @unlink($oldestFile);
        }

        // Rotate existing files
        for ($i = $this->maxFiles - 1; $i >= 1; $i--) {
            $oldFile = $this->path . '.' . $i;
            $newFile = $this->path . '.' . ($i + 1);

            if (file_exists($oldFile)) {
                @rename($oldFile, $newFile);
            }
        }

        // Rotate the current file
        @rename($this->path, $this->path . '.1');
    }

    /**
     * Get the log file path.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get the maximum file size before rotation.
     *
     * @return int Size in bytes
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * Get the maximum number of rotated files to keep.
     *
     * @return int
     */
    public function getMaxFiles(): int
    {
        return $this->maxFiles;
    }
}
