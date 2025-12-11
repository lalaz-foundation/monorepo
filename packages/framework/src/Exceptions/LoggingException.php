<?php

declare(strict_types=1);

namespace Lalaz\Exceptions;

use Throwable;

/**
 * Exception thrown for logging-related errors.
 *
 * Use this exception when:
 * - Log writers cannot write to destination
 * - Log channels are misconfigured
 * - Log file permissions are insufficient
 * - Log facade is used before initialization
 *
 * Example usage:
 * ```php
 * // Write failure
 * throw LoggingException::writeError('/var/log/app.log', 'Disk full');
 *
 * // Directory creation failure
 * throw LoggingException::directoryCreationFailed('/var/log/app');
 *
 * // Logger not initialized
 * throw LoggingException::notInitialized();
 *
 * // Invalid channel
 * throw LoggingException::invalidChannel('custom', ['app', 'error', 'debug']);
 * ```
 *
 * @package lalaz/framework
 * @author Gregory Serrao <hi@lalaz.dev>
 * @link https://lalaz.dev
 */
class LoggingException extends FrameworkException
{
    /**
     * Create exception for write failures.
     *
     * Thrown when a log writer cannot write to its destination
     * (file, stream, network, etc.).
     *
     * @param string $destination The log destination (file path, URL, etc.)
     * @param string|null $reason Description of why writing failed
     * @param Throwable|null $previous Previous exception for chaining
     * @return self The exception instance
     */
    public static function writeError(string $destination, ?string $reason = null, ?Throwable $previous = null): self
    {
        $message = "Failed to write to log destination '{$destination}'.";

        if ($reason !== null) {
            $message .= " {$reason}";
        }

        return new self(
            $message,
            ['destination' => $destination, 'reason' => $reason],
            $previous,
        );
    }

    /**
     * Create exception for directory creation failures.
     *
     * Thrown when the logging system cannot create a directory
     * for log files.
     *
     * @param string $directory The directory path that could not be created
     * @return self The exception instance
     */
    public static function directoryCreationFailed(string $directory): self
    {
        return new self(
            "Failed to create log directory '{$directory}'.",
            ['directory' => $directory],
        );
    }

    /**
     * Create exception when logger is not initialized.
     *
     * Thrown when the Log facade is used before a logger instance
     * has been configured.
     *
     * @return self The exception instance
     */
    public static function notInitialized(): self
    {
        return new self(
            'Logger has not been initialized. Call Log::setLogger() first.',
            ['hint' => 'Ensure LogServiceProvider is registered in your application.'],
        );
    }

    /**
     * Create exception for invalid log channel.
     *
     * Thrown when attempting to use a log channel that is not
     * configured in the logging configuration.
     *
     * @param string $channel The invalid channel name
     * @param array<int, string> $availableChannels List of valid channel names
     * @return self The exception instance
     */
    public static function invalidChannel(string $channel, array $availableChannels = []): self
    {
        return new self(
            "Invalid log channel '{$channel}'.",
            [
                'channel' => $channel,
                'available_channels' => $availableChannels,
            ],
        );
    }

    /**
     * Create exception for permission errors.
     *
     * Thrown when the logging system does not have sufficient
     * permissions to perform an operation.
     *
     * @param string $path The path with permission issues
     * @param string $operation The operation that failed (read, write, create)
     * @return self The exception instance
     */
    public static function permissionDenied(string $path, string $operation): self
    {
        return new self(
            "Permission denied: cannot {$operation} '{$path}'.",
            ['path' => $path, 'operation' => $operation],
        );
    }
}
